<?php

declare(strict_types=1);

namespace App\Services\Locale;

use Config\Locale as LocaleConfig;

/**
 * Picks the interface language for a request (PRD LANG-03): explicit choice (?lang= or the
 * cookie it leaves) → country from a trusted proxy header → Accept-Language → Indonesian.
 * Indonesia gets `id`, every other country `en`. Pure: the caller passes the request values.
 */
final class LocaleResolver
{
    private LocaleConfig $config;

    public function __construct(?LocaleConfig $config = null)
    {
        $this->config = $config ?? config('Locale');
    }

    public function supports(?string $locale): bool
    {
        return $locale !== null && in_array($locale, $this->config->supported, true);
    }

    /**
     * @param string|null $choice         ?lang= value or the language cookie
     * @param string|null $country        trusted country header value (already checked by the caller)
     * @param string      $acceptLanguage raw Accept-Language header
     */
    public function resolve(?string $choice, ?string $country, string $acceptLanguage): string
    {
        if ($this->supports($choice)) {
            return $choice;
        }

        $country = strtoupper(trim((string) $country));
        // XX/T1 are Cloudflare's "unknown" and Tor markers, not countries.
        if (preg_match('/\A[A-Z]{2}\z/', $country) === 1 && !in_array($country, ['XX', 'T1'], true)) {
            return $country === 'ID' ? 'id' : 'en';
        }

        return $this->fromAcceptLanguage($acceptLanguage) ?? $this->config->fallback;
    }

    private function fromAcceptLanguage(string $header): ?string
    {
        $best = null;
        $bestWeight = 0.0;
        foreach (explode(',', $header) as $part) {
            $pieces = explode(';', trim($part));
            $tag = strtolower(trim($pieces[0]));
            $weight = 1.0;
            foreach (array_slice($pieces, 1) as $param) {
                if (preg_match('/\A\s*q=([01](?:\.\d{1,3})?)\s*\z/', $param, $m) === 1) {
                    $weight = (float) $m[1];
                }
            }
            // "in" is the old code for Indonesian that some browsers still send.
            $language = explode('-', $tag)[0] === 'in' ? 'id' : explode('-', $tag)[0];
            if ($weight > $bestWeight && $this->supports($language)) {
                $best = $language;
                $bestWeight = $weight;
            }
        }

        return $best;
    }
}
