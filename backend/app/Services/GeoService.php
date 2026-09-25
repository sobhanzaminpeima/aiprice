<?php

namespace App\Services;

/**
 * North Cyprus town-center coordinates (public geographic data) plus a
 * small deterministic jitter per listing so pins in the same town don't
 * stack exactly on top of each other on the map. The jitter is seeded by
 * the listing's own id/title — same listing always lands on the same spot,
 * never a random position that shifts on reload.
 */
class GeoService
{
    private const CITY_COORDS = [
        'girne' => [35.3417, 33.3167],       // Kyrenia
        'lefkosa' => [35.1856, 33.3823],     // Nicosia
        'gazimağusa' => [35.1246, 33.9391],  // Famagusta
        'gazimagusa' => [35.1246, 33.9391],
        'iskele' => [35.2867, 33.9167],       // Trikomo
        'guzelyurt' => [35.2000, 32.9833],    // Morphou
        'lefke' => [35.1167, 32.8500],
    ];

    // North Cyprus bounding box — used by the frontend map to lock panning/zoom.
    public const BOUNDS = [
        'south' => 35.00, 'west' => 32.25,
        'north' => 35.75, 'east' => 34.60,
    ];
    public const CENTER = [35.28, 33.45];

    public function coordinatesFor(?string $city, string $seed): ?array
    {
        if (!$city) {
            return null;
        }
        $key = mb_strtolower(trim($city));
        if (!isset(self::CITY_COORDS[$key])) {
            return null;
        }

        [$lat, $lng] = self::CITY_COORDS[$key];

        // Deterministic jitter within ~2km so markers spread out but stay stable.
        $hash = crc32($seed);
        $jitterLat = ((($hash % 2000) / 1000) - 1) * 0.018;
        $jitterLng = (((intdiv($hash, 2000) % 2000) / 1000) - 1) * 0.018;

        return [round($lat + $jitterLat, 6), round($lng + $jitterLng, 6)];
    }
}
