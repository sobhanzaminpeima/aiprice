<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeding_does_not_create_the_documented_admin(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertDatabaseMissing('users', ['email' => 'admin@aiprice.app']);
    }

    public function test_readiness_command_rejects_local_configuration(): void
    {
        $this->artisan('app:check-production')->assertFailed();
    }
}
