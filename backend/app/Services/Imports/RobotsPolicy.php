<?php

namespace App\Services\Imports;

/**
 * Minimal robots.txt evaluation: picks the most specific user-agent group that
 * matches our token (falling back to "*") and applies longest-match
 * Allow/Disallow. Also exposes Content-Signal values for reporting.
 */
final class RobotsPolicy
{
    /** @param list<array{allow: bool, path: string}> $rules */
    private function __construct(private array $rules, public readonly array $contentSignals) {}

    public static function parse(string $txt, string $agentToken): self
    {
        $groups = [];
        $current = null;
        $lastWasAgent = false;
        $signals = [];
        foreach (preg_split('/\R/', $txt) ?: [] as $line) {
            $line = trim(preg_replace('/#.*/', '', $line) ?? '');
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                if (! $lastWasAgent) {
                    $current = count($groups);
                    $groups[$current] = ['agents' => [], 'rules' => []];
                }
                $groups[$current]['agents'][] = strtolower($value);
                $lastWasAgent = true;

                continue;
            }
            $lastWasAgent = false;
            if ($current === null) {
                continue;
            }
            if ($field === 'allow' || $field === 'disallow') {
                if ($value !== '') {
                    $groups[$current]['rules'][] = ['allow' => $field === 'allow', 'path' => $value];
                }
            } elseif ($field === 'content-signal') {
                foreach (explode(',', $value) as $pair) {
                    [$k, $v] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, null);
                    if ($k !== '') {
                        $signals[$k] = $v;
                    }
                }
            }
        }

        $token = strtolower($agentToken);
        $match = null;
        foreach ($groups as $g) {
            foreach ($g['agents'] as $a) {
                if ($a !== '*' && str_contains($token, $a)) {
                    $match = $g;
                }
            }
        }
        if (! $match) {
            foreach ($groups as $g) {
                if (in_array('*', $g['agents'], true)) {
                    $match = $g;
                }
            }
        }

        return new self($match['rules'] ?? [], $signals);
    }

    public function allows(string $path): bool
    {
        $best = null;
        foreach ($this->rules as $rule) {
            $pattern = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule['path'], '#')).'#';
            if (preg_match($pattern, $path) && ($best === null || strlen($rule['path']) > strlen($best['path'])
                || (strlen($rule['path']) === strlen($best['path']) && $rule['allow']))) {
                $best = $rule;
            }
        }

        return $best === null || $best['allow'];
    }
}
