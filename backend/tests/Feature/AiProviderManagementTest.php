<?php

namespace Tests\Feature;

use App\Models\{AiProviderConnection, AuditLog, User};
use App\Services\AI\ProviderRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

class AiProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function settings(): array
    {
        return ['api_key' => 'fixture-private-provider-key', 'model' => 'Qwen/Qwen3.8-Flash', 'enabled' => true, 'active' => true];
    }

    public function test_keys_are_encrypted_hidden_and_never_written_to_audit(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/v1/admin/llm/providers/runcrate', $this->settings())->assertOk();
        $this->assertNotSame($this->settings()['api_key'], DB::table('ai_provider_connections')->value('api_key'));
        $this->assertStringNotContainsString('fixture-private', AiProviderConnection::firstOrFail()->toJson());
        $this->assertStringNotContainsString('fixture-private', AuditLog::where('action', 'ai_provider.updated')->firstOrFail()->toJson());
        $this->getJson('/api/v1/admin/llm/providers')->assertOk()->assertJsonPath('0.active', true)->assertJsonMissingPath('0.api_key');
        $this->assertSame('fixture-private-provider-key', app(ProviderRegistry::class)->resolve()['key']);
        $data=$this->settings();unset($data['api_key']);
        $this->putJson('/api/v1/admin/llm/providers/runcrate', $data)->assertOk();
        $this->assertSame('fixture-private-provider-key', app(ProviderRegistry::class)->resolve()['key']);
    }

    public function test_active_provider_drives_extraction_and_disabled_provider_sends_nothing(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/v1/admin/llm/providers/runcrate', $this->settings())->assertOk();
        Http::fake(['api.runcrate.ai/*' => Http::response(['choices'=>[['message'=>['content'=>'{"type":"vehicle","title":"2021 Toyota Corolla","price":16500,"currency":"GBP"}']]]])]);
        $this->postJson('/api/v1/listings/extract', ['text'=>'2021 Toyota Corolla for GBP 16500.'])->assertOk();
        Http::assertSent(fn($request)=>$request->url()==='https://api.runcrate.ai/v1/chat/completions' && $request->hasHeader('Authorization','Bearer fixture-private-provider-key'));
        Http::fake();
        $this->putJson('/api/v1/admin/llm/providers/runcrate', array_replace($this->settings(), ['enabled'=>false]))->assertOk();
        $this->postJson('/api/v1/listings/extract', ['text'=>'2021 Toyota Corolla for GBP 16500.'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_tests_record_safe_health_and_preserve_selected_model(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/v1/admin/llm/providers/runcrate', $this->settings());
        Http::fake(['api.runcrate.ai/*'=>Http::sequence()->push(['error'=>['message'=>'secret upstream detail']],529)->push(['choices'=>[['message'=>['content'=>'OK']]]])]);
        $data=['provider'=>'runcrate','model'=>'openai/gpt-oss-120b','prompt'=>'Reply OK'];
        $this->postJson('/api/v1/admin/llm/test',$data)->assertUnprocessable()->assertJsonPath('http_status',529)->assertDontSee('secret upstream');
        $this->assertSame('provider_overloaded', AiProviderConnection::firstOrFail()->last_test_result);
        $this->postJson('/api/v1/admin/llm/test',$data)->assertOk();
        $this->assertSame('Qwen/Qwen3.8-Flash', AiProviderConnection::firstOrFail()->model);
        $this->assertSame('success', AiProviderConnection::firstOrFail()->last_test_result);
    }

    public function test_only_settings_managers_can_change_credentials_and_unknown_hosts_are_rejected(): void
    {
        $analyst=User::factory()->create(['role'=>'ai_analyst']);$analyst->assignRole('ai_analyst');
        $this->actingAs($analyst,'sanctum')->getJson('/api/v1/admin/llm/providers')->assertOk();
        $this->putJson('/api/v1/admin/llm/providers/runcrate',$this->settings())->assertForbidden();
        $this->actingAs($this->admin(),'sanctum')->putJson('/api/v1/admin/llm/providers/localhost',$this->settings())->assertNotFound();
        $this->putJson('/api/v1/admin/llm/providers/runcrate',array_replace($this->settings(),['model'=>'unknown-model']))->assertUnprocessable();
        $this->putJson('/api/v1/admin/llm/providers/openai',['api_key'=>'fixture-openai-key','model'=>'gpt-4o-mini','active'=>true,'enabled'=>true])->assertOk();
        $this->putJson('/api/v1/admin/llm/providers/runcrate',$this->settings())->assertOk();
        $this->assertSame(1,AiProviderConnection::where('is_active',true)->count());
    }
}
