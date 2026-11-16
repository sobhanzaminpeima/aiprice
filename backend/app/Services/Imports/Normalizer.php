<?php

namespace App\Services\Imports;

use Illuminate\Support\Carbon;

/**
 * Pure value normalization for imported listings. Unknown or unparsable input
 * always returns null — never a guessed value.
 */
final class Normalizer
{
    public const CURRENCIES = ['GBP' => 'GBP', 'STG' => 'GBP', '£' => 'GBP', 'EUR' => 'EUR', '€' => 'EUR',
        'USD' => 'USD', '$' => 'USD', 'TL' => 'TRY', 'TRY' => 'TRY', '₺' => 'TRY'];

    private const MONTHS_TR = ['ocak' => 1, 'şubat' => 2, 'mart' => 3, 'nisan' => 4, 'mayıs' => 5, 'haziran' => 6,
        'temmuz' => 7, 'ağustos' => 8, 'eylül' => 9, 'ekim' => 10, 'kasım' => 11, 'aralık' => 12];

    private const FUEL = ['benzin' => 'petrol', 'dizel' => 'diesel', 'hibrit' => 'hybrid', 'hybrid' => 'hybrid',
        'elektrik' => 'electric', 'elektrikli' => 'electric', 'lpg' => 'lpg', 'benzin & lpg' => 'petrol_lpg',
        'benzin/lpg' => 'petrol_lpg', 'plug-in hibrit' => 'plug_in_hybrid'];

    private const TRANSMISSION = ['otomatik' => 'automatic', 'düz' => 'manual', 'manuel' => 'manual',
        'yarı otomatik' => 'semi_automatic', 'yari otomatik' => 'semi_automatic', 'cvt' => 'cvt'];

    private const STEERING = ['sağ direksiyon' => 'right', 'sol direksiyon' => 'left', 'sağ' => 'right', 'sol' => 'left'];

    private const COLORS = ['beyaz' => 'White', 'siyah' => 'Black', 'gri' => 'Grey', 'gümüş' => 'Silver',
        'gümüş gri' => 'Silver', 'füme' => 'Dark grey', 'kırmızı' => 'Red', 'mavi' => 'Blue', 'lacivert' => 'Navy',
        'yeşil' => 'Green', 'sarı' => 'Yellow', 'turuncu' => 'Orange', 'kahverengi' => 'Brown', 'bej' => 'Beige',
        'bordo' => 'Burgundy', 'mor' => 'Purple', 'altın' => 'Gold', 'şampanya' => 'Champagne', 'pembe' => 'Pink',
        'turkuaz' => 'Turquoise', 'inci beyazı' => 'Pearl white'];

    private const BODY = ['sedan' => 'sedan', 'hatchback' => 'hatchback', 'hatchback 5 kapı' => 'hatchback',
        'hatchback 3 kapı' => 'hatchback', 'crossover' => 'crossover', 'suv' => 'suv', 'arazi' => 'suv',
        'station wagon' => 'estate', 'coupe' => 'coupe', 'cabrio' => 'convertible', 'roadster' => 'convertible',
        'mpv' => 'mpv', 'minivan' => 'mpv', 'pick-up' => 'pickup', 'pickup' => 'pickup', 'panelvan' => 'van',
        'kamyonet' => 'van'];

    /** @return array{amount: float, currency: string}|null */
    public static function price(?string $text): ?array
    {
        $text = trim((string) $text);
        if (! preg_match('/([\d][\d.,\s]*)\s*(GBP|STG|EUR|USD|TL|TRY|£|€|\$|₺)/u', $text, $m)
            && ! preg_match('/(GBP|STG|EUR|USD|TL|TRY|£|€|\$|₺)\s*([\d][\d.,\s]*)/u', $text, $m2)) {
            return null;
        }
        [$number, $symbol] = isset($m[1]) ? [$m[1], $m[2]] : [$m2[2], $m2[1]];
        $amount = self::decimal($number);
        if ($amount === null || $amount <= 0) {
            return null;
        }

        return ['amount' => $amount, 'currency' => self::CURRENCIES[strtoupper($symbol)] ?? self::CURRENCIES[$symbol]];
    }

    /** Turkish number format: "1.488.449,18" → 1488449.18, "22.300" → 22300. */
    public static function decimal(?string $text): ?float
    {
        $s = preg_replace('/[^\d.,]/', '', (string) $text) ?? '';
        if ($s === '') {
            return null;
        }
        $comma = strrpos($s, ',');
        $dot = strrpos($s, '.');
        if ($comma !== false && $dot !== false) {
            // Both present: the right-most one is the decimal separator.
            [$thousands, $decimal] = $comma > $dot ? ['.', ','] : [',', '.'];
            $s = str_replace([$thousands, $decimal], ['', '.'], $s);
        } elseif ($comma !== false || $dot !== false) {
            $sep = $comma !== false ? ',' : '.';
            // "22.300" / "15,000" are thousands; "18,5" / "1.5" are decimals.
            $s = preg_match('/^\d{1,3}(\\'.$sep.'\d{3})+$/', $s) ? str_replace($sep, '', $s) : str_replace($sep, '.', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    public static function year(mixed $value, ?int $now = null): ?int
    {
        if (! preg_match('/\b(19[5-9]\d|20\d{2})\b/', (string) $value, $m)) {
            return null;
        }
        $year = (int) $m[1];
        $max = ($now ?? (int) date('Y')) + 1;

        return $year <= $max ? $year : null;
    }

    public static function mileage(mixed $value): ?int
    {
        $s = trim((string) $value);
        if ($s === '' || ! preg_match('/\d/', $s)) {
            return null;
        }
        $miles = (bool) preg_match('/\b(mil|miles|mi)\b/iu', $s);
        $n = self::decimal(preg_replace('/[^\d.,]/', '', $s));
        if ($n === null || $n < 0 || $n > 2_000_000) {
            return null;
        }

        return (int) round($miles ? $n * 1.609344 : $n);
    }

    public static function fuel(?string $v): ?string
    {
        return self::lookup(self::FUEL, $v);
    }

    public static function transmission(?string $v): ?string
    {
        return self::lookup(self::TRANSMISSION, $v);
    }

    public static function steering(?string $v): ?string
    {
        return self::lookup(self::STEERING, $v);
    }

    public static function color(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : (self::lookup(self::COLORS, $v) ?? mb_convert_case($v, MB_CASE_TITLE));
    }

    public static function body(?string $v): ?string
    {
        return self::lookup(self::BODY, $v);
    }

    /** "2.El (Plakasız)" → [used, unregistered]; "Sıfır" → [new, null]. */
    public static function condition(?string $v): array
    {
        $s = self::lower((string) $v);
        if ($s === '') {
            return ['condition' => null, 'registration_status' => null];
        }
        $condition = str_contains($s, 'sıfır') || str_contains($s, '0 km') ? 'new' : (str_contains($s, '2.el') || str_contains($s, 'ikinci') ? 'used' : null);
        $registration = str_contains($s, 'plakasız') ? 'unregistered' : (str_contains($s, 'plakalı') ? 'registered' : null);

        return ['condition' => $condition, 'registration_status' => $registration];
    }

    /** "16 Kasım 2026" → 2026-11-16. */
    public static function turkishDate(?string $v): ?string
    {
        if (! preg_match('/(\d{1,2})\s+(\p{L}+)\s+(\d{4})/u', (string) $v, $m)) {
            return null;
        }
        $month = self::MONTHS_TR[self::lower($m[2])] ?? null;
        if (! $month || ! checkdate($month, (int) $m[1], (int) $m[3])) {
            return null;
        }

        return Carbon::create((int) $m[3], $month, (int) $m[1])->toDateString();
    }

    /** "Mercedes - Benz" → "Mercedes-Benz". */
    public static function make(?string $v): ?string
    {
        $v = trim(preg_replace('/\s*-\s*/', '-', (string) $v) ?? '');

        return $v === '' ? null : $v;
    }

    public static function intOrNull(mixed $v, int $min = 1, int $max = PHP_INT_MAX): ?int
    {
        if (! preg_match('/\d+/', (string) $v, $m)) {
            return null;
        }
        $n = (int) $m[0];

        return $n >= $min && $n <= $max ? $n : null;
    }

    /**
     * North Cyprus / Turkey numbers to E.164. Returns null for anything that is
     * masked or not a full number — never fills in missing digits.
     */
    public static function phoneE164(?string $v): ?string
    {
        $raw = trim((string) $v);
        if ($raw === '' || preg_match('/[*•xX]/u', $raw)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($raw, '+')) {
            return strlen($digits) >= 10 && strlen($digits) <= 15 ? '+'.$digits : null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);

            return strlen($digits) >= 10 ? '+'.$digits : null;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '+90'.substr($digits, 1);
        }
        if (strlen($digits) === 10 && in_array($digits[0], ['3', '5'], true)) {
            return '+90'.$digits;
        }

        return null;
    }

    public static function lower(string $s): string
    {
        return mb_strtolower(trim(str_replace(['I', 'İ'], ['ı', 'i'], $s)), 'UTF-8');
    }

    private static function lookup(array $map, ?string $v): ?string
    {
        $key = self::lower((string) $v);

        return $key === '' ? null : ($map[$key] ?? null);
    }
}
