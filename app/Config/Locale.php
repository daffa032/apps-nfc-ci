<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Interface language (PRD LANG-01–03). The visitor's choice is kept in a cookie; without one the
 * first language is guessed from the visitor's country (a trusted proxy header, or a local
 * GeoLite2 Country database), then from the browser's Accept-Language, then Indonesian.
 */
class Locale extends BaseConfig
{
    /**
     * @var list<string>
     */
    public array $supported = ['id', 'en'];

    public string $fallback = 'id';

    public string $cookieName = 'nfc_lang';

    /**
     * Header carrying the visitor's ISO country code, e.g. 'CF-IPCountry' behind Cloudflare.
     * Empty means no trusted proxy adds one, so the header is ignored: a browser can send any
     * value, so it is only read when the deployment's proxy sets it and strips the client's copy.
     */
    public string $countryHeader = '';

    /**
     * Local MaxMind GeoLite2 Country database used when no country header is configured (cPanel
     * hosting has none). Outside the public folder; download it with a free MaxMind licence and
     * refresh it regularly (see guides/DEVELOPMENT.md). A missing file just skips the IP guess.
     * Set to '' to turn the lookup off.
     */
    public string $geoipDatabase = WRITEPATH . 'geoip/GeoLite2-Country.mmdb';

    /**
     * MaxMind account for `php spark geoip:update` (free GeoLite2 licence). Set in the private .env
     * as locale.geoipAccountId / locale.geoipLicenseKey; never commit or print them.
     */
    public string $geoipAccountId = '';
    public string $geoipLicenseKey = '';
}
