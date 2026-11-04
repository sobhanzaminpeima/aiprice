<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\User;
use App\Notifications\KycReviewed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    private const ASSIGNABLE_ROLES = 'buyer,seller,dealer,agency,investor,premium,moderator,support,finance_manager,ai_analyst,admin';

    public function index(Request $request): JsonResponse
    {
        $q = User::withTrashed()->orderByDesc('created_at');
        if ($request->role) {
            $q->where('role', $request->role);
        }
        if ($request->search) {
            $q->where(function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        return response()->json($q->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|in:'.self::ASSIGNABLE_ROLES,
            'phone' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:255',
        ]);

        $this->guardPrivilegedRole($request->user(), $v['role']);

        $user = User::create([
            'name' => $v['name'], 'email' => $v['email'], 'password' => Hash::make($v['password']),
            'role' => $v['role'], 'phone' => $v['phone'] ?? null,
            'is_active' => true, 'email_verified_at' => now(),
            // An admin-chosen password is a shared secret; make the owner replace it.
            'force_password_change' => true,
        ]);
        $user->assignRole($v['role']);
        $user->profile()->create(['business_name' => $v['business_name'] ?? null]);

        return response()->json(['user' => $user->load('profile'), 'message' => 'User created.'], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(User::withTrashed()->with(['profile', 'kyc', 'listings', 'activeSubscription.plan'])->findOrFail($id));
    }

    public function toggleActive(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->guardTarget($request->user(), $user);

        $user->update(['is_active' => ! $user->is_active]);

        // Login refuses inactive users, but tokens issued before the suspension
        // would otherwise keep working until they expire.
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json(['is_active' => $user->is_active]);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $request->validate(['role' => 'required|in:'.self::ASSIGNABLE_ROLES]);
        $user = User::findOrFail($id);

        $this->guardTarget($request->user(), $user);
        $this->guardPrivilegedRole($request->user(), $request->role);

        $user->update(['role' => $request->role]);
        $user->syncRoles([$request->role]);

        // Permissions travel with the token's session in the SPA; force a
        // fresh login so a demoted user can't keep using a cached menu.
        $user->tokens()->delete();

        return response()->json(['message' => 'Role updated.', 'user' => $user]);
    }

    public function kycQueue(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');

        return response()->json(
            KycVerification::with('user:id,name,email,role,created_at')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderBy($status === 'pending' ? 'created_at' : 'updated_at', $status === 'pending' ? 'asc' : 'desc')
                ->paginate(20)
        );
    }

    public function updateAgency(Request $request, string $id): JsonResponse
    {
        $user = User::whereIn('role', ['agency', 'dealer'])->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => 'sometimes|required|in:agency,dealer',
            'is_active' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($user, $data) {
            $user->update($data);
            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }
            if (isset($data['role']) || ! $user->is_active) {
                $user->tokens()->delete();
            }

            return response()->json($user->fresh(['profile', 'activeSubscription.plan']));
        });
    }

    public function verifyAgency(Request $request, string $id): JsonResponse
    {
        $user = User::whereIn('role', ['agency', 'dealer'])->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $user->update(['is_verified' => true]);

        return response()->json(['message' => 'Agency verified.']);
    }

    public function approveKyc(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $kyc = KycVerification::where('user_id', $user->id)->where('status', 'pending')->latest()->first();
        $kyc?->update(['status' => 'approved', 'verified_at' => now(), 'rejection_reason' => null]);

        $user->update(['kyc_verified' => true]);
        $user->notify(new KycReviewed(approved: true));

        return response()->json(['message' => 'KYC approved.']);
    }

    public function rejectKyc(Request $request, string $id): JsonResponse
    {
        $v = $request->validate(['reason' => 'required|string|max:500']);
        $user = User::findOrFail($id);

        $kyc = KycVerification::where('user_id', $user->id)->where('status', 'pending')->latest()->firstOrFail();
        $kyc->update(['status' => 'rejected', 'rejection_reason' => $v['reason']]);

        $user->update(['kyc_verified' => false]);
        $user->notify(new KycReviewed(approved: false, reason: $v['reason']));

        return response()->json(['message' => 'KYC rejected.']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->guardTarget($request->user(), $user);

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    /** Nobody edits their own account from here, and only super_admin touches another admin. */
    private function guardTarget(User $actor, User $target): void
    {
        abort_if($actor->id === $target->id, 403, 'You cannot change your own account from the admin panel.');

        if (in_array($target->role, User::PRIVILEGED_ROLES, true) && ! $actor->isSuperAdmin()) {
            abort(403, 'Only a super admin can modify another admin.');
        }
    }

    private function guardPrivilegedRole(User $actor, string $role): void
    {
        if (in_array($role, User::PRIVILEGED_ROLES, true) && ! $actor->isSuperAdmin()) {
            abort(403, 'Only a super admin can grant admin access.');
        }
    }
}
