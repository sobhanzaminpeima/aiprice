<?php

namespace App\Services;

/**
 * North Cyprus town-center coordinates (public geographic data) plus a
 * small deterministic jitter per listing so pins in the same town don't
 * stack exactly on top of each other on the map. The jitter is seeded by
 * the listing's own id/title, so the same listing always lands on the same
 * spot. Turkish, English and Greek-derived town names are all accepted.
 */
class GeoService
{
    private const CITY_COORDS = [
        'girne' => [35.3364, 33.3192], 'kyrenia' => [35.3364, 33.3192],
        'lefkosa' => [35.1856, 33.3823], 'nicosia' => [35.1856, 33.3823],
        'gazimagusa' => [35.1246, 33.9391], 'magusa' => [35.1246, 33.9391], 'famagusta' => [35.1246, 33.9391],
        'iskele' => [35.2867, 33.8917], 'trikomo' => [35.2867, 33.8917], 'long beach' => [35.2420, 33.9010],
        'guzelyurt' => [35.2000, 32.9933], 'morphou' => [35.2000, 32.9933],
        'lefke' => [35.1167, 32.8500],
        'alsancak' => [35.3420, 33.1970], 'lapta' => [35.3360, 33.1660], 'karsiyaka' => [35.3480, 33.1010],
        'catalkoy' => [35.3230, 33.3790], 'ozankoy' => [35.3150, 33.3650], 'karaoglanoglu' => [35.3410, 33.2620],
        'bellapais' => [35.3060, 33.3550], 'beylerbeyi' => [35.3060, 33.3550],
        'esentepe' => [35.3330, 33.5800], 'tatlisu' => [35.3780, 33.7580], 'bahceli' => [35.3530, 33.6580],
        'bogaz' => [35.3000, 33.9600], 'yeni iskele' => [35.2700, 33.9200],
        'karpaz' => [35.5720, 34.3780], 'dipkarpaz' => [35.5990, 34.3800], 'yenierenkoy' => [35.5340, 34.1900],
        'mehmetcik' => [35.4300, 34.0700], 'gecitkale' => [35.2350, 33.7250],
        'gonyeli' => [35.2170, 33.3100], 'hamitkoy' => [35.2050, 33.3600], 'alaykoy' => [35.1830, 33.2580],
        'degirmenlik' => [35.2520, 33.4700], 'haspolat' => [35.2050, 33.4200], 'kucuk kaymakli' => [35.1870, 33.3520],
        'tuzla' => [35.1570, 33.8890], 'yenibogazici' => [35.1700, 33.8850], 'mutluyaka' => [35.1950, 33.8850],
    ];

    // North Cyprus bounding box: the frontend map locks panning/zoom to it.
    public const BOUNDS = [
        'south' => 35.00, 'west' => 32.25,
        'north' => 35.75, 'east' => 34.60,
    ];

    public const CENTER = [35.28, 33.45];

    public static function normalise(string $city): string
    {
        $c = mb_strtolower(trim($city));
        $c = strtr($c, ['ğ' => 'g', 'ş' => 's', 'ı' => 'i', 'i̇' => 'i', 'ö' => 'o', 'ü' => 'u', 'ç' => 'c', 'â' => 'a', '-' => ' ']);
        $c = preg_replace('/\s*\(.*\)\s*/u', '', $c) ?? $c;   // "Girne (Kyrenia)" -> "girne"

        return preg_replace('/\s+/', ' ', trim($c)) ?? $c;
    }

    public function coordinatesFor(?string $city, string $seed): ?array
    {
        if (! $city) {
            return null;
        }
        $key = self::normalise($city);
        $coords = self::CITY_COORDS[$key] ?? null;
        if (! $coords) {
            // "Girne Merkez", "Iskele Long Beach" ... match on the first known town name.
            foreach (self::CITY_COORDS as $name => $c) {
                if (str_contains($key, $name)) {
                    $coords = $c;
                    break;
                }
            }
        }
        if (! $coords) {
            return null;
        }

        [$lat, $lng] = $coords;

        // Deterministic jitter within ~1.5 km so markers spread out but stay stable.
        $hash = crc32($seed);
        $jitterLat = ((($hash % 2000) / 1000) - 1) * 0.012;
        $jitterLng = (((intdiv($hash, 2000) % 2000) / 1000) - 1) * 0.012;

        return [round($lat + $jitterLat, 6), round($lng + $jitterLng, 6)];
    }
}
