<?php

declare(strict_types=1);

namespace App\Services\Locale;

use App\Libraries\MaxMindReader;
use Throwable;

/**
 * Visitor country for the first-language guess (PRD LANG-03), from the local GeoLite2 Country file.
 * Never throws and never stores the IP: a missing/broken database, a private or invalid address,
 * or an unknown range all return null, so the page renders normally with the next fallback.
 */
class CountryLookup
{
    private ?MaxMindReader $reader = null;
    private bool $opened = false;

    public function __construct(private string $databasePath)
    {
    }

    public function countryFor(string $ip): ?string
    {
        // Private, loopback and reserved ranges have no country.
        if ($this->databasePath === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }
        try {
            if (!$this->opened) {
                $this->opened = true;
                $this->reader = is_file($this->databasePath) ? new MaxMindReader($this->databasePath) : null;
            }

            return $this->reader?->countryCode($ip);
        } catch (Throwable $exception) {
            log_message('warning', 'GeoIP lookup unavailable: {message}', ['message' => $exception->getMessage()]);
            $this->reader = null;

            return null;
        }
    }
}
