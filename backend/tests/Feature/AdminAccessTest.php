<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPermission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public static function accessMatrix(): array
    {
        // endpoint => roles that must get through; every other staff role must be refused.
        return [
            'dashboard' => ['/admin/dashboard', ['admin', 'moderator', 'support', 'finance_manager', 'ai_analyst']],
            'listings' => ['/admin/listings', ['admin', 'moderator']],
            'users' => ['/admin/users', ['admin']],
            'settings' => ['/admin/settings', ['admin']],
            'llm providers' => ['/admin/llm/providers', ['admin', 'ai_analyst']],
            'subscriptions' => ['/admin/subscriptions', ['admin', 'finance_manager']],
            'inquiries' => ['/admin/inquiries', ['admin', 'support']],
            'ai usage' => ['/admin/analytics/ai-usage', ['admin', 'finance_manager', 'ai_analyst']],
            'kyc queue' => ['/admin/kyc', ['admin']],
        ];
    }

    #[DataProvider('accessMatrix')]
    public function test_staff_roles_only_reach_their_sections(string $uri, array $allowed): void
    {
        foreach (['admin', 'moderator', 'support', 'finance_manager', 'ai_analyst'] as $role) {
            $expected = in_array($role, $allowed, true) ? 200 : 403;
            $this->actingAs($this->userWithRole($role), 'sanctum')
                ->getJson('/api/v1'.$uri)
                ->assertStatus($expected, "{$role} on {$uri}");
        }
    }

    public function test_super_admin_reaches_everything(): void
    {
        $super = $this->userWithRole('super_admin');
        foreach (self::accessMatrix() as [$uri]) {
            $this->actingAs($super, 'sanctum')->getJson('/api/v1'.$uri)->assertOk();
        }
    }

    public function test_non_staff_are_refused(): void
    {
        foreach (['buyer', 'seller', 'dealer', 'agency', 'investor'] as $role) {
            $this->actingAs($this->userWithRole($role), 'sanctum')
                ->getJson('/api/v1/admin/dashboard')
                ->assertForbidden();
        }
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_every_admin_route_has_a_permission(): void
    {
        $unmapped = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => str_starts_with($uri, 'api/v1/admin/'))
            ->reject(fn ($uri) => EnsureAdminPermission::permissionFor(preg_replace('/\{[^}]+\}/', 'x', $uri)) !== null)
            ->values();

        $this->assertSame([], $unmapped->all(), 'Admin routes without a permission entry: '.$unmapped->implode(', '));
    }

    public function test_admin_cannot_escalate_or_touch_other_admins(): void
    {
        $admin = $this->userWithRole('admin');
        $super = $this->userWithRole('super_admin');
        $moderator = $this->userWithRole('moderator');

        $this->actingAs($admin, 'sanctum');

        $this->putJson("/api/v1/admin/users/{$super->id}/role", ['role' => 'buyer'])->assertForbidden();
        $this->putJson("/api/v1/admin/users/{$moderator->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/users/{$super->id}")->assertForbidden();
        $this->putJson("/api/v1/admin/users/{$admin->id}/role", ['role' => 'buyer'])->assertForbidden();
        $this->postJson('/api/v1/admin/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'Secret@1234', 'role' => 'admin',
        ])->assertForbidden();

        $this->putJson("/api/v1/admin/users/{$moderator->id}/role", ['role' => 'support'])->assertOk();
    }

    public function test_super_admin_can_grant_admin(): void
    {
        $moderator = $this->userWithRole('moderator');

        $this->actingAs($this->userWithRole('super_admin'), 'sanctum')
            ->putJson("/api/v1/admin/users/{$moderator->id}/role", ['role' => 'admin'])
            ->assertOk();

        $this->assertTrue($moderator->fresh()->hasRole('admin'));
    }

    public function test_suspending_a_user_revokes_their_tokens(): void
    {
        $buyer = $this->userWithRole('buyer');
        $token = $buyer->createToken('auth')->plainTextToken;

        $this->actingAs($this->userWithRole('super_admin'), 'sanctum')
            ->postJson("/api/v1/admin/users/{$buyer->id}/toggle-active")
            ->assertOk();

        $this->assertSame(0, $buyer->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_exposes_permissions_for_the_menu(): void
    {
        $this->actingAs($this->userWithRole('support'), 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('is_staff', true)
            ->assertJsonPath('permissions', ['view_dashboard', 'manage_support']);
    }

    public function test_agency_endpoint_cannot_grant_staff_or_modify_an_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $agency = $this->userWithRole('agency');
        $super = $this->userWithRole('super_admin');
        $this->actingAs($admin, 'sanctum');
        $this->putJson("/api/v1/admin/agencies/{$agency->id}", ['role' => 'super_admin'])->assertUnprocessable();
        $this->putJson("/api/v1/admin/agencies/{$super->id}", ['name' => 'Compromised'])->assertNotFound();
        $this->postJson("/api/v1/admin/agencies/{$super->id}/verify")->assertNotFound();
        $this->assertSame('agency', $agency->fresh()->role);
    }

    public function test_agency_role_changes_sync_permissions_and_suspension_revokes_tokens(): void
    {
        $agency = $this->userWithRole('agency');
        $agency->createToken('agency-session');
        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->putJson("/api/v1/admin/agencies/{$agency->id}", ['role' => 'dealer', 'is_active' => false])->assertOk();
        $this->assertTrue($agency->fresh()->hasRole('dealer'));
        $this->assertFalse($agency->fresh()->hasRole('agency'));
        $this->assertSame(0, $agency->tokens()->count());
    }

    public function test_business_verification_does_not_approve_identity_documents(): void
    {
        $agency = $this->userWithRole('agency');
        $this->actingAs($this->userWithRole('admin'), 'sanctum')
            ->postJson("/api/v1/admin/agencies/{$agency->id}/verify")->assertOk();
        $this->assertTrue($agency->fresh()->is_verified);
        $this->assertFalse($agency->fresh()->kyc_verified);
    }
}
