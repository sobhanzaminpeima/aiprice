<?php

namespace App\Support;

/**
 * Keeps generated and imported text on-brand and honest.
 *
 * Imported data (e.g. authorized KKTCarabam exports) and LLM output must never
 * show another marketplace's name ("KKTC Arabam", "kktcarabam.com", …) to
 * users, and must never present an AI price as guaranteed or verified.
 */
final class BrandGuard
{
    public const BRAND = 'AIPrice.app';

    /** Appended to every LLM prompt that produces user-facing text. */
    public const PROMPT_RULES = <<<'TXT'
Brand and honesty rules (mandatory):
- The platform is called AIPrice.app. Never mention any other website, marketplace or data source by name (for example KKTC Arabam, KKTCarabam, kktcarabam.com, Sahibinden, Arabam) and never output their URLs.
- AI prices are estimates based on comparable listings. Never call a price "verified", "guaranteed", "certified" or "official".
TXT;

    /** Third-party marketplace names (Latin + Persian spellings) and their domains. */
    private const THIRD_PARTY = [
        '/\bhttps?:\/\/(?:www\.)?(?:kktcarabam|arabam|sahibinden)\.com\S*/iu',
        '/\b(?:www\.)?(?:kktcarabam|arabam|sahibinden)\.com\b\S*/iu',
        '/\bKKTC\s*-?\s*Araba(?:m)?\b/iu',
        '/\bKKT\s*Carabam\b/iu',
        '/\bKKTCarabam\b/iu',
        '/کاکاتج\s*عربا(?:م)?/u',
        '/کا\s*کا\s*تی\s*سی\s*عربا(?:م)?/u',
    ];

    /** Phrases that overstate what an AI estimate is. */
    private const OVERCLAIMS = [
        '/\bAI[- ]verified (?:fair )?pric(?:e|ing)\b/iu' => 'AI price estimate',
        '/\bverified (?:fair )?price\b/iu' => 'estimated fair price',
        '/\bguaranteed (?:fair )?price\b/iu' => 'estimated fair price',
    ];

    public static function scrub(?string $text): string
    {
        $text = (string) $text;
        $text = preg_replace('/\s*Source:\s*\S*(?:kktcarabam|arabam|sahibinden)\S*/iu', '', $text) ?? $text;
        $text = preg_replace(self::THIRD_PARTY, self::BRAND, $text) ?? $text;
        // Collapse "AIPrice.app AIPrice.app" produced by adjacent replacements.
        $text = preg_replace('/(AIPrice\.app)(?:[\s,.\-]+AIPrice\.app)+/u', '$1', $text) ?? $text;
        foreach (self::OVERCLAIMS as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return trim(preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text);
    }

    public static function mentionsThirdParty(?string $text): bool
    {
        foreach (self::THIRD_PARTY as $pattern) {
            if (preg_match($pattern, (string) $text)) {
                return true;
            }
        }

        return false;
    }
}
