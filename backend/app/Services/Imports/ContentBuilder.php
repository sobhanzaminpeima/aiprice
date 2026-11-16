<?php

namespace App\Services\Imports;

/**
 * Original, factual AIPrice copy built only from normalized fields. The
 * seller's own description is never republished verbatim; facts it states
 * explicitly reach the listing only through structured fields (e.g. features).
 */
final class ContentBuilder
{
    public const UNVERIFIED_NOTE = 'Details are provided by the seller and have not been independently verified by AIPrice. Please confirm the vehicle\'s condition, history and documents before buying.';

    private const FUEL = ['petrol' => 'Petrol', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Electric',
        'lpg' => 'LPG', 'petrol_lpg' => 'Petrol/LPG', 'plug_in_hybrid' => 'Plug-in hybrid'];

    private const TRANSMISSION = ['automatic' => 'Automatic', 'manual' => 'Manual', 'semi_automatic' => 'Semi-automatic', 'cvt' => 'CVT'];

    public static function fuelLabel(?string $v): ?string
    {
        return $v ? (self::FUEL[$v] ?? ucfirst($v)) : null;
    }

    public static function transmissionLabel(?string $v): ?string
    {
        return $v ? (self::TRANSMISSION[$v] ?? ucfirst($v)) : null;
    }

    /** Values stored on the shared vehicles table (the UI capitalizes them). */
    public static function vehicleFuel(?string $v): ?string
    {
        return $v ? str_replace(['petrol_lpg', 'plug_in_hybrid'], ['petrol/lpg', 'plug-in hybrid'], $v) : null;
    }

    public static function vehicleTransmission(?string $v): ?string
    {
        return $v ? str_replace('semi_automatic', 'semi-automatic', $v) : null;
    }

    public function title(array $d): string
    {
        $parts = array_filter([
            $d['model_year'], $d['make'], $d['model'], $d['variant'] ?? null,
        ]);
        $title = implode(' ', $parts);
        $extras = array_filter([self::fuelLabel($d['fuel_type'] ?? null), self::transmissionLabel($d['transmission'] ?? null)]);

        return mb_substr($extras ? $title.' · '.implode(' · ', $extras) : $title, 0, 190);
    }

    public function description(array $d): string
    {
        $name = trim(implode(' ', array_filter([$d['model_year'], $d['make'], $d['model'], $d['variant'] ?? null])));
        $lines = [];

        $intro = "{$name} for sale".($d['city'] ? " in {$d['city']}" : '').', North Cyprus.';
        $lines[] = $intro;

        $specs = array_filter([
            'Mileage' => $d['mileage'] !== null ? number_format($d['mileage']).' km'
                .((($d['mileage_as_advertised']['unit'] ?? 'km') === 'mi') ? ' ('.number_format($d['mileage_as_advertised']['value']).' miles as advertised)' : '') : null,
            'Fuel' => self::fuelLabel($d['fuel_type']),
            'Transmission' => self::transmissionLabel($d['transmission']),
            'Engine' => $d['engine_cc'] ? number_format($d['engine_cc']).' cc' : null,
            'Power' => $d['horsepower'] ? $d['horsepower'].' hp' : null,
            'Body' => $d['body_type'] ? ucfirst($d['body_type']) : null,
            'Colour' => $d['exterior_color'],
            'Steering' => $d['steering'] ? ucfirst($d['steering']).'-hand drive' : null,
            'Drivetrain' => $d['drivetrain'] ?? null,
            'Registration' => match ($d['registration_status'] ?? null) {
                'unregistered' => 'Not yet registered locally (no plates)',
                'registered' => 'Registered (plated)',
                default => null,
            },
        ]);
        $lines[] = '';
        $lines[] = 'Key specifications (as stated by the seller):';
        foreach ($specs as $label => $value) {
            $lines[] = "• {$label}: {$value}";
        }

        if (! empty($d['features'])) {
            $lines[] = '';
            $lines[] = 'Equipment mentioned by the seller: '.implode(', ', $d['features']).'.';
        }

        $lines[] = '';
        $lines[] = 'Asking price: '.number_format((float) $d['asking_price']).' '.$d['currency'].' (seller\'s price). '
            .'Any AIPrice market estimate is shown separately and is not the seller\'s price.';
        $lines[] = '';
        $lines[] = self::UNVERIFIED_NOTE;

        return implode("\n", $lines);
    }

    /** @return array{meta_title: string, meta_description: string} */
    public function seo(array $d): array
    {
        $name = trim(implode(' ', array_filter([$d['model_year'], $d['make'], $d['model']])));
        $bits = array_filter([
            $d['mileage'] !== null ? number_format($d['mileage']).' km' : null,
            self::fuelLabel($d['fuel_type']), self::transmissionLabel($d['transmission']), $d['city'],
        ]);

        return [
            'meta_title' => mb_substr("{$name} for sale – ".number_format((float) $d['asking_price']).' '.$d['currency'].' | AIPrice', 0, 70),
            'meta_description' => mb_substr("Used {$name}: ".implode(', ', $bits).'. Seller asking price '
                .number_format((float) $d['asking_price']).' '.$d['currency'].'. Compare with AIPrice market data.', 0, 160),
        ];
    }
}
