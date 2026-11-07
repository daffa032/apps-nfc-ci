<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Visit statistics for Pro cards (PRD section 6). Set analytics.secret in the private .env to a
 * long random string; without it, unique-visitor estimates are disabled (views are still counted).
 */
class Analytics extends BaseConfig
{
    /**
     * HMAC key for the daily anonymous visitor hash. Never logged or shown.
     */
    public string $secret = '';

    /**
     * Days a daily visitor hash is kept before it is deleted.
     */
    public int $visitorRetentionDays = 30;

    /**
     * Months of daily aggregates kept.
     */
    public int $metricsRetentionMonths = 12;
}
