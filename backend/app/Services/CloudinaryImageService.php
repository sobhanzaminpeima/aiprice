<?php

namespace App\Services;

use App\Models\Listing;

/**
 * Builds a designed 1080x1350 Instagram post image URL using Cloudinary's
 * on-the-fly transformations (fetch mode). The source listing photo is pulled
 * from its public URL, cropped, darkened with a brand scrim, then overlaid with
 * the AIPrice.app logo, the price, title and key specs.
 *
 * One-time setup: upload the brand logo (white version) to Cloudinary with
 * public_id matching CLOUDINARY_LOGO_ID (default "aiprice_logo").
 */
class CloudinaryImageService
{
    private string $cloud;
    private string $logoId;

    public function __construct()
    {
        $this->cloud  = (string) config('services.cloudinary.cloud_name', '');
        $this->logoId = (string) config('services.cloudinary.logo_id', 'aiprice_logo');
    }

    public function enabled(): bool
    {
        return $this->cloud !== '';
    }

    /**
     * @param string $sourceImage public URL of the chosen listing photo
     */
    public function buildPostImage(Listing $listing, string $sourceImage): string
    {
        $price   = $this->formatPrice($listing->price, $listing->currency);
        $title   = $this->clean($listing->title, 40);
        $city    = $this->clean($listing->city ?: 'North Cyprus', 28);
        $specs   = $this->clean($this->specsLine($listing), 42);
        $battery = (int) ($listing->price_battery_percent ?? 0);
        $tag     = strtoupper($listing->type === 'vehicle' ? 'vehicle' : 'property');

        $t = [];
        // 1. Crop to IG portrait, smart gravity
        $t[] = 'c_fill,g_auto,w_1080,h_1350,q_auto,f_jpg';
        // 2. Bottom scrim for legibility (dark brand panel)
        $t[] = 'l_one_pixel,co_rgb:0a0a0a,o_62/c_scale,w_1080,h_560/fl_layer_apply,g_south';
        // 3. Top scrim (lighter) for logo + tag
        $t[] = 'l_one_pixel,co_rgb:0a0a0a,o_38/c_scale,w_1080,h_240/fl_layer_apply,g_north';
        // 4. Brand accent bar above the title block
        $t[] = 'l_one_pixel,co_rgb:00e6b0/c_scale,w_120,h_8/fl_layer_apply,g_south_west,x_60,y_330';
        // 5. Logo (top-left)
        $t[] = 'l_' . $this->logoId . '/c_scale,w_150/fl_layer_apply,g_north_west,x_56,y_52';
        // 6. Type tag (top-right)
        $t[] = 'l_text:Arial_30_bold:' . $this->enc($tag) . ',co_rgb:00e6b0/fl_layer_apply,g_north_east,x_64,y_70';
        // 7. Price (big, bottom)
        $t[] = 'l_text:Arial_82_bold:' . $this->enc($price) . ',co_rgb:ffffff/fl_layer_apply,g_south_west,x_58,y_232';
        // 8. Title
        $t[] = 'l_text:Arial_44:' . $this->enc($title) . ',co_rgb:ffffff/fl_layer_apply,g_south_west,x_60,y_168';
        // 9. Specs line
        $t[] = 'l_text:Arial_32:' . $this->enc($specs) . ',co_rgb:c9cdd2/fl_layer_apply,g_south_west,x_60,y_112';
        // 10. City + AI battery
        $footer = $city . '   •   AI score ' . $battery . '%';
        $t[] = 'l_text:Arial_28:' . $this->enc($footer) . ',co_rgb:00bfff/fl_layer_apply,g_south_west,x_60,y_56';

        $transform = implode('/', $t);

        return sprintf(
            'https://res.cloudinary.com/%s/image/fetch/%s/%s',
            $this->cloud,
            $transform,
            rawurlencode($sourceImage)
        );
    }

    private function specsLine(Listing $listing): string
    {
        $l = $listing->listable;
        $parts = [];
        if ($listing->type === 'vehicle' && $l) {
            if (!empty($l->year)) $parts[] = $l->year;
            if (!empty($l->mileage)) $parts[] = number_format((int) $l->mileage) . ' km';
            if (!empty($l->fuel_type)) $parts[] = ucfirst($l->fuel_type);
            if (!empty($l->transmission)) $parts[] = ucfirst($l->transmission);
        } elseif ($l) {
            if (!empty($l->bedrooms)) $parts[] = $l->bedrooms . ' bed';
            if (!empty($l->bathrooms)) $parts[] = $l->bathrooms . ' bath';
            if (!empty($l->area_sqft)) $parts[] = number_format((int) $l->area_sqft) . ' m2';
        }
        return implode('  |  ', $parts) ?: 'Verified listing';
    }

    private function formatPrice($price, ?string $currency): string
    {
        $sym = match (strtoupper((string) $currency)) {
            'GBP' => '£', 'USD' => '$', 'EUR' => '€', 'TRY' => 'TRY ', default => (string) $currency . ' ',
        };
        return $sym . number_format((float) $price);
    }

    /** Cloudinary text-layer encoding: strip slashes/commas, then URL-encode. */
    private function enc(string $text): string
    {
        $text = str_replace(['/', ',', '%'], [' ', ' ', ' '], $text);
        return rawurlencode($text);
    }

    private function clean(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max - 1) . '…';
        }
        return $text;
    }
}
