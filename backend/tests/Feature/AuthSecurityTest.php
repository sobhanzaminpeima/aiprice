<?php

namespace Tests\Feature;

use App\Models\KycVerification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_is_rate_limited_per_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_password_reset_round_trip_revokes_sessions(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com', 'is_active' => true]);
        $user->createToken('old-device');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token, $user) {
            $token = $n->token;
            // The link must point at the SPA, not a Laravel web route.
            $url = call_user_func(ResetPassword::$createUrlCallback, $user, $n->token);
            return str_starts_with($url, config('app.frontend_url').'/reset-password?token=');
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token, 'email' => 'reset@example.com',
            'password' => 'Brand@New123', 'password_confirmation' => 'Brand@New123',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['email' => 'reset@example.com', 'password' => 'Brand@New123'])->assertOk();
    }

    public function test_reset_rejects_a_bad_token(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'bogus', 'email' => 'reset@example.com',
            'password' => 'Brand@New123', 'password_confirmation' => 'Brand@New123',
        ])->assertStatus(422);
    }

    public function test_kyc_submission_lifecycle(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'kyc_verified' => false]);
        $buyer->assignRole('buyer');

        $this->actingAs($buyer, 'sanctum');
        $this->getJson('/api/v1/kyc')->assertOk()->assertJsonPath('status', 'not_submitted');

        $this->postJson('/api/v1/kyc', ['document_type' => 'passport', 'document_number' => 'A1'])->assertCreated();
        $this->postJson('/api/v1/kyc', ['document_type' => 'passport', 'document_number' => 'A1'])->assertStatus(422);
        $this->getJson('/api/v1/kyc')->assertJsonPath('status', 'pending');

        $reviewer = User::factory()->create(['role' => 'admin']);
        $reviewer->assignRole('admin');
        $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/v1/admin/users/{$buyer->id}/reject-kyc", ['reason' => 'Blurry photo'])
            ->assertOk();

        $this->assertSame('rejected', KycVerification::where('user_id', $buyer->id)->value('status'));
        $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/kyc')->assertJsonPath('kyc.rejection_reason', 'Blurry photo');
    }

    public function test_forced_password_change_blocks_everything_else(): void
    {
        $user = User::factory()->create(['force_password_change' => true, 'password' => bcrypt('Temp@12345')]);
        $user->assignRole('buyer');
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/my-listings')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/v1/auth/me')->assertOk();

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Temp@12345', 'password' => 'Mine@123456', 'password_confirmation' => 'Mine@123456',
        ])->assertOk();

        $this->getJson('/api/v1/my-listings')->assertOk();
    }

    public function test_unknown_api_path_is_a_json_404(): void
    {
        $this->getJson('/api/v1/definitely-not-a-route')->assertNotFound();
    }
}
