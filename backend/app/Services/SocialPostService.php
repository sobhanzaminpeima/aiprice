<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Support\BrandGuard;

/**
 * Orchestrates turning a Listing into a ready-to-review Instagram post:
 *   1. pick the best photo
 *   2. render the designed post image (Cloudinary)
 *   3. generate a viral caption + 5 hashtags (OpenAI, with a safe fallback)
 *
 * The resulting SocialPost is saved as a "draft" — nothing is published until
 * an admin approves it.
 */
class SocialPostService
{
    public function __construct(
        private CloudinaryImageService $images,
        private InstagramService $instagram,
    ) {}

    public function generateForListing(Listing $listing): SocialPost
    {
        $listing->loadMissing('listable');

        $best     = $this->pickBestPhoto($listing);
        $imageUrl = $this->images->enabled() && $best ? $this->images->buildPostImage($listing, $best) : $best;
        [$caption, $hashtags] = $this->generateCaption($listing);

        return SocialPost::updateOrCreate(
            ['listing_id' => $listing->id, 'platform' => 'instagram', 'status' => 'draft'],
            [
                'source_image' => $best,
                'image_url'    => $imageUrl,
                'caption'      => $caption,
                'hashtags'     => $hashtags,
                'error'        => null,
            ]
        );
    }

    /** Choose the best image: prefer thumbnail, else the first/widest available. */
    public function pickBestPhoto(Listing $listing): ?string
    {
        $images = is_array($listing->images) ? array_values(array_filter($listing->images)) : [];
        if (empty($images)) {
            return $listing->thumbnail ?: null;
        }
        // Heuristic: prefer a non-thumbnail, reasonably sized URL; fall back to first.
        // (A future upgrade can score these with GPT-4o Vision.)
        return $listing->thumbnail ?: $images[0];
    }

    /**
     * Publish a previously-approved post. Marks status along the way.
     * @throws \RuntimeException on failure (status set to "failed").
     */
    public function publish(SocialPost $post): SocialPost
    {
        $post->update(['status' => 'publishing', 'error' => null]);
        try {
            $caption = trim($post->caption . "\n\n" . collect($post->hashtags ?? [])->implode(' '));
            $result  = $this->instagram->publishImage($post->image_url, $caption);
            $post->update([
                'status'       => 'published',
                'ig_media_id'  => $result['media_id'],
                'ig_permalink' => $result['permalink'],
                'published_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $post->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
        return $post;
    }

    /** @return array{0:string,1:array<string>} [caption, hashtags] */
    private function generateCaption(Listing $listing): array
    {
        $fallbackTags = $listing->type === 'vehicle'
            ? ['#NorthCyprus', '#CarsForSale', '#CyprusCars', '#CarDeals', '#AIPriceApp']
            : ['#NorthCyprus', '#CyprusProperty', '#RealEstate', '#PropertyForSale', '#AIPriceApp'];

        $key = (string) config('services.openai.key', '');
        if ($key === '') {
            return [$this->fallbackCaption($listing), $fallbackTags];
        }

        $price = $listing->currency . ' ' . number_format((float) $listing->price);
        $rules = BrandGuard::PROMPT_RULES;
        $safeTitle = BrandGuard::scrub($listing->title);
        $prompt = <<<P
You are a viral social media manager for AIPrice.app, an AI-powered marketplace in North Cyprus.
Write an Instagram caption for this listing:
- Title: {$safeTitle}
- Type: {$listing->type}
- Price: {$price}
- City: {$listing->city}
- AI price-fairness estimate: {$listing->price_battery_percent}/100

Rules:
- 2-3 short punchy lines, premium and exciting tone, tasteful emojis.
- Say the price was checked with AIPrice.app's AI price estimate (an estimate, not a guarantee).
- End with a call to action.
- Then EXACTLY 5 viral, relevant hashtags.

{$rules}

Respond ONLY as JSON: {"caption":"...","hashtags":["#a","#b","#c","#d","#e"]}
P;

        try {
            $res = Http::withToken($key)->timeout(20)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => config('services.openai.model', 'gpt-4o-mini'),
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.8,
                'max_tokens'  => 300,
            ]);
            if ($res->successful()) {
                $content = preg_replace('/```json?\s*|\s*```/', '', trim($res->json('choices.0.message.content', '')));
                $data = json_decode($content, true);
                if (is_array($data) && !empty($data['caption'])) {
                    $tags = array_slice(array_map(fn($h) => str_starts_with($h, '#') ? $h : '#' . $h, $data['hashtags'] ?? $fallbackTags), 0, 5);
                    return [BrandGuard::scrub($data['caption']), $tags ?: $fallbackTags];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Caption generation failed: ' . $e->getMessage());
        }
        return [$this->fallbackCaption($listing), $fallbackTags];
    }

    private function fallbackCaption(Listing $listing): string
    {
        $price = $listing->currency . ' ' . number_format((float) $listing->price);
        $emoji = $listing->type === 'vehicle' ? '🚗' : '🏡';
        $title = BrandGuard::scrub($listing->title);
        return "{$emoji} {$title}\n💰 {$price} · AI price estimate\n📍 {$listing->city}, North Cyprus\n\nFind the real market value before you buy 👉 AIPrice.app";
    }
}
