<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Publishes images to an Instagram Business account via the Meta Graph API.
 *
 * Two-step flow:
 *   1. Create a media container (image_url + caption)
 *   2. Publish the container
 *
 * Requirements: IG Business account ID + long-lived access token with
 * instagram_content_publish permission. The image MUST be a public URL.
 */
class InstagramService
{
    private string $userId;
    private string $token;
    private string $version;

    public function __construct()
    {
        $this->userId  = (string) config('services.instagram.user_id', '');
        $this->token   = (string) config('services.instagram.access_token', '');
        $this->version = (string) config('services.instagram.graph_version', 'v21.0');
    }

    public function enabled(): bool
    {
        return $this->userId !== '' && $this->token !== '';
    }

    private function base(): string
    {
        return "https://graph.facebook.com/{$this->version}";
    }

    /**
     * Publish an image post. Returns ['media_id' => ..., 'permalink' => ...].
     * @throws \RuntimeException on any API failure.
     */
    public function publishImage(string $imageUrl, string $caption): array
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('Instagram is not configured (missing IG_USER_ID or IG_ACCESS_TOKEN).');
        }

        // Step 1 — create container
        $create = Http::asForm()->timeout(30)->post("{$this->base()}/{$this->userId}/media", [
            'image_url'    => $imageUrl,
            'caption'      => $caption,
            'access_token' => $this->token,
        ]);

        if (!$create->successful() || !$create->json('id')) {
            $msg = $create->json('error.message') ?? $create->body();
            Log::warning('IG container failed: ' . $msg);
            throw new \RuntimeException('Instagram container error: ' . $msg);
        }
        $creationId = $create->json('id');

        // Step 2 — publish container
        $publish = Http::asForm()->timeout(30)->post("{$this->base()}/{$this->userId}/media_publish", [
            'creation_id'  => $creationId,
            'access_token' => $this->token,
        ]);

        if (!$publish->successful() || !$publish->json('id')) {
            $msg = $publish->json('error.message') ?? $publish->body();
            Log::warning('IG publish failed: ' . $msg);
            throw new \RuntimeException('Instagram publish error: ' . $msg);
        }
        $mediaId = $publish->json('id');

        // Best-effort permalink
        $permalink = null;
        try {
            $info = Http::timeout(15)->get("{$this->base()}/{$mediaId}", [
                'fields'       => 'permalink',
                'access_token' => $this->token,
            ]);
            $permalink = $info->json('permalink');
        } catch (\Throwable $e) {
            // permalink is optional
        }

        return ['media_id' => $mediaId, 'permalink' => $permalink];
    }

    /** Quick connectivity check for the admin "Test connection" button. */
    public function testConnection(): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'message' => 'Missing IG_USER_ID or IG_ACCESS_TOKEN.'];
        }
        $res = Http::timeout(15)->get("{$this->base()}/{$this->userId}", [
            'fields'       => 'username,followers_count',
            'access_token' => $this->token,
        ]);
        if ($res->successful() && $res->json('username')) {
            return ['ok' => true, 'username' => $res->json('username'), 'followers' => $res->json('followers_count')];
        }
        return ['ok' => false, 'message' => $res->json('error.message') ?? $res->body()];
    }
}
