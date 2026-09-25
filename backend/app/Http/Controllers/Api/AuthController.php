<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'role' => 'sometimes|in:buyer,seller,dealer,agency,investor',
            'phone' => 'nullable|string|max:20',
        ]);
        $user = User::create(['name' => $v['name'], 'email' => $v['email'], 'password' => Hash::make($v['password']), 'role' => $v['role'] ?? 'buyer', 'phone' => $v['phone'] ?? null]);
        UserProfile::create(['user_id' => $user->id]);
        $user->assignRole($user->role);
        return response()->json(['user' => $user, 'token' => $user->createToken('auth')->plainTextToken, 'token_type' => 'Bearer'], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $v = $request->validate(['email' => 'required|email', 'password' => 'required']);
        if (!Auth::attempt($v)) throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        $user = Auth::user();
        if (!$user->is_active) { Auth::logout(); return response()->json(['message' => 'Account suspended.'], 403); }
        $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);
        return response()->json([
            'user' => $this->withAccess($user->load('profile')),
            'token' => $user->createToken('auth')->plainTextToken,
            'force_password_change' => $user->force_password_change,
        ]);
    }

    /** The SPA builds its admin menu from these, so it never has to hardcode role names. */
    private function withAccess(User $user): array
    {
        return $user->toArray() + [
            'is_staff' => $user->isStaff(),
            'permissions' => $user->permissionNames(),
        ];
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->withAccess($request->user()->load('profile', 'kyc', 'activeSubscription.plan')));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));
        return response()->json(['message' => 'Reset link sent if email exists.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'force_password_change' => false,
                ])->save();

                // Whoever triggered the reset may be locking out a thief —
                // kill every existing session.
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Password has been reset. You can now sign in.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate(['current_password' => 'required', 'password' => 'required|min:8|confirmed']);
        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) return response()->json(['message' => 'Current password incorrect.'], 422);
        $user->update(['password' => Hash::make($request->password), 'force_password_change' => false]);
        // Sign out every other device; keep the session that made the change.
        // Session-authenticated requests carry a TransientToken with no id.
        $currentId = $user->currentAccessToken()?->id ?? null;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();
        return response()->json(['message' => 'Password changed.']);
    }
}
