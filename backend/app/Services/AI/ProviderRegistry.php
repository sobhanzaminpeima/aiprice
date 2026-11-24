<?php

namespace App\Services\AI;

use App\Models\AiProviderConnection;

/** All runtime AI clients resolve credentials here, including queue workers. */
class ProviderRegistry
{
    public const PROVIDERS = ['runcrate', 'openai', 'nvidia'];

    public function resolve(?string $provider = null): array
    {
        $provider ??= AiProviderConnection::where('is_active', true)->value('provider') ?? config('services.ai_provider', 'openai');
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException('Unsupported AI provider.');
        }
        $row = AiProviderConnection::where('provider', $provider)->first();
        $enabled = $row?->enabled ?? true;
        return [
            'provider' => $provider,
            'key' => $enabled ? ($row?->api_key ?? (string) config("services.$provider.key", '')) : '',
            'model' => $row?->model ?? config("services.$provider.model"),
            'base_url' => rtrim((string) config("services.$provider.base_url"), '/'),
            'enabled' => $enabled,
        ];
    }

    public function summaries(): array
    {
        $active = $this->resolve()['provider'];
        return array_map(function ($provider) use ($active) {
            $config = $this->resolve($provider);
            $row = AiProviderConnection::where('provider', $provider)->first();
            return [
                'provider' => $provider, 'configured' => $config['key'] !== '', 'enabled' => $config['enabled'],
                'model' => $config['model'], 'models' => config("services.$provider.models", [$config['model']]),
                'base_url' => $config['base_url'], 'active' => $active === $provider,
                'last_http_status' => $row?->last_http_status, 'last_test_result' => $row?->last_test_result,
                'tested_at' => $row?->tested_at?->toIso8601String(),
                'source' => $row ? 'database' : 'environment',
            ];
        }, self::PROVIDERS);
    }
}
