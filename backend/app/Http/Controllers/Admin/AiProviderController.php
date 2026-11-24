<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AiProviderConnection, AuditLog};
use App\Services\AI\ProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Validation\Rule;

class AiProviderController extends Controller
{
    public function index(ProviderRegistry $registry)
    {
        return response()->json($registry->summaries());
    }

    public function update(Request $request, string $provider, ProviderRegistry $registry)
    {
        abort_unless($request->user()->can('manage_settings'), 403);
        abort_unless(in_array($provider, ProviderRegistry::PROVIDERS, true), 404);
        $data = $request->validate([
            'model' => ['required', 'string', 'max:150', $provider === 'runcrate' ? Rule::in(config('services.runcrate.models')) : 'regex:/^[a-zA-Z0-9_.\/:\-]+$/'],
            'api_key' => 'nullable|string|min:12|max:1000|regex:/^\S+$/',
            'enabled' => 'required|boolean', 'active' => 'required|boolean',
        ]);
        DB::transaction(function () use ($data, $provider, $request, $registry) {
            $before = collect($registry->summaries())->firstWhere('provider', $provider);
            $row = AiProviderConnection::firstOrNew(['provider' => $provider]);
            if ($data['active']) AiProviderConnection::where('provider', '!=', $provider)->where('is_active', true)->update(['is_active' => false]);
            $row->fill(['model' => $data['model'], 'enabled' => $data['enabled'], 'is_active' => $data['active'] || $before['active']]);
            if (!empty($data['api_key'])) $row->api_key = $data['api_key'];
            $row->last_http_status = null;
            $row->last_test_result = null;
            $row->tested_at = null;
            $row->save();
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => 'ai_provider.updated',
                'model_type' => AiProviderConnection::class, 'model_id' => $row->id,
                'old_values' => array_intersect_key($before, array_flip(['provider', 'model', 'enabled', 'active'])),
                'new_values' => ['provider' => $provider, 'model' => $row->model, 'enabled' => $row->enabled, 'active' => $row->is_active, 'key_changed' => !empty($data['api_key'])],
                'ip_address' => $request->ip(),
            ]);
        });
        return $this->index($registry);
    }

    public function test(Request $request, ProviderRegistry $registry)
    {
        $data = $request->validate([
            'prompt' => 'required|string|max:500',
            'provider' => ['nullable', Rule::in(ProviderRegistry::PROVIDERS)],
            'model' => 'nullable|string|max:150',
            'api_key' => 'nullable|string|min:12|max:1000|regex:/^\S+$/',
        ]);
        $providedKey = !empty($data['api_key']);
        if ($providedKey) abort_unless($request->user()->can('manage_settings'), 403);
        $config = $registry->resolve($data['provider'] ?? null);
        $provider = $config['provider'];
        $model = $data['model'] ?? $config['model'];
        if ($provider === 'runcrate') abort_unless(in_array($model, config('services.runcrate.models'), true), 422, 'Unsupported Runcrate model.');
        $key = $providedKey ? $data['api_key'] : $config['key'];
        if (!$config['enabled'] || $key === '') return response()->json(['message' => 'Provider is disabled or has no API key.'], 422);
        $status = null;
        $reply = null;
        try {
            $response = Http::withToken($key)->acceptJson()->connectTimeout(8)->timeout(25)->post($config['base_url'].'/chat/completions', [
                'model' => $model, 'messages' => [['role' => 'user', 'content' => $data['prompt']]], 'max_tokens' => 200,
            ]);
            $status = $response->status();
            $reply = $response->json('choices.0.message.content');
            $ok = $response->successful() && is_string($reply) && trim($reply) !== '';
            $result = $ok ? 'success' : ($status === 529 ? 'provider_overloaded' : 'provider_rejected_or_empty');
        } catch (\Illuminate\Http\Client\ConnectionException) {
            $ok = false;
            $result = 'connection_timeout';
        }
        // Testing an unsaved key must not overwrite the saved connection's health.
        if (!$providedKey) {
            $row = AiProviderConnection::firstOrNew(['provider' => $provider]);
            if (!$row->exists) $row->fill(['model' => $config['model'], 'enabled' => $config['enabled']]);
            $row->fill(['last_http_status' => $status, 'last_test_result' => $result, 'tested_at' => now()])->save();
        }
        $details = ['credential_source' => $providedKey ? 'entered' : 'saved', 'key_saved' => !$providedKey, 'provider' => $provider, 'model' => $model];
        if (!$ok) return response()->json(['message' => 'AI connection test failed: '.$result, 'http_status' => $status] + $details, 422);
        return response()->json(['response' => mb_substr($reply, 0, 2000)] + $details);
    }
}
