<?php

namespace App\Services\Imports;

/**
 * Recognises standard equipment named in seller text (Turkish or English) and
 * returns neutral English labels. Only explicit mentions are matched; nothing
 * is inferred from trim names.
 */
final class FeatureDictionary
{
    private const TERMS = [
        'head up display' => 'Head-up display',
        'hayalet ekran' => 'Head-up display',
        'koltuk ısıtma' => 'Heated seats',
        'isitmali koltuk' => 'Heated seats',
        'ısıtmalı koltuk' => 'Heated seats',
        'otomatik bagaj' => 'Power tailgate',
        'elektrikli bagaj' => 'Power tailgate',
        'deri koltuk' => 'Leather seats',
        'start stop' => 'Start-stop system',
        'anahtarsız' => 'Keyless entry',
        'keyless' => 'Keyless entry',
        'cruise control' => 'Cruise control',
        'hız sabitleyici' => 'Cruise control',
        'adaptive cruise' => 'Adaptive cruise control',
        'şerit takip' => 'Lane keeping assist',
        'şerit koruma' => 'Lane keeping assist',
        'çarpışma önleyici' => 'Collision avoidance assist',
        'mesafe takip' => 'Distance control',
        'yağmur sensörü' => 'Rain sensor',
        'park sensörü' => 'Parking sensors',
        '360 derece kamera' => '360° camera',
        '360 kamera' => '360° camera',
        'geri görüş kamera' => 'Reversing camera',
        'geri vites kamera' => 'Reversing camera',
        'arka kamera' => 'Reversing camera',
        'bluetooth' => 'Bluetooth',
        'navigasyon' => 'Navigation',
        'navigation' => 'Navigation',
        'apple carplay' => 'Apple CarPlay',
        'carplay' => 'Apple CarPlay',
        'android auto' => 'Android Auto',
        'sunroof' => 'Sunroof',
        'cam tavan' => 'Panoramic roof',
        'panoramik' => 'Panoramic roof',
        'dijital klima' => 'Automatic climate control',
        'otomatik klima' => 'Automatic climate control',
        'klima' => 'Air conditioning',
        'led far' => 'LED headlights',
        'xenon' => 'Xenon headlights',
        'kör nokta' => 'Blind spot monitoring',
        'blind spot' => 'Blind spot monitoring',
        'elektrikli koltuk' => 'Power seats',
        'hafızalı koltuk' => 'Memory seats',
        'alaşım jant' => 'Alloy wheels',
        'çelik jant' => 'Steel wheels',
        'push start' => 'Push-button start',
        'düğmeli çalıştırma' => 'Push-button start',
        'dümende radyo' => 'Steering wheel controls',
        'direksiyon kumanda' => 'Steering wheel controls',
        'katlanır ayna' => 'Power-folding mirrors',
        'katlanabilen' => 'Power-folding mirrors',
        '4x4' => 'Four-wheel drive',
        '4wd' => 'Four-wheel drive',
        'awd' => 'All-wheel drive',
        'isofix' => 'ISOFIX child-seat mounts',
    ];

    /** @return list<string> */
    public static function extract(string ...$texts): array
    {
        $haystack = Normalizer::lower(implode("\n", $texts));
        // Normalise dotted/undotted i so "Isıtma" and "ısıtma" both match.
        $found = [];
        foreach (self::TERMS as $term => $label) {
            if (str_contains($haystack, $term)) {
                $found[$label] = true;
            }
        }
        // A specific climate label makes the generic one redundant.
        if (isset($found['Automatic climate control'])) {
            unset($found['Air conditioning']);
        }

        return array_keys($found);
    }

    /** Drivetrain only when the seller states it explicitly. */
    public static function drivetrain(string ...$texts): ?string
    {
        $h = Normalizer::lower(implode("\n", $texts));

        return match (true) {
            (bool) preg_match('/\b(4x4|4wd)\b/u', $h) => '4WD',
            (bool) preg_match('/\bawd\b/u', $h) => 'AWD',
            (bool) preg_match('/\b(2wd|4x2)\b/u', $h) => '2WD',
            default => null,
        };
    }
}
