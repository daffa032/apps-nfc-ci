<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Midtrans Snap (PRD section 7, BI-01..03). Keys live only in the private .env (midtrans.*);
 * never in source, docs, HTML, JS, query strings, logs or the audit table.
 */
class Midtrans extends BaseConfig
{
    /** false = sandbox. Production keys must never be used with false, and the reverse. */
    public bool $isProduction = false;

    /** Server key: backend only (Snap API, status API, notification signature). */
    public string $serverKey = '';

    /** Client key: not secret, kept for completeness (redirect flow does not need it). */
    public string $clientKey = '';

    /** Minutes before an unpaid order expires at the gateway (PRD decision: 24 hours). */
    public int $expiryMinutes = 1440;

    /** Prefix of order_code so sandbox and production orders never look alike. */
    public string $orderPrefix = 'NFC';

    public function snapUrl(): string
    {
        return $this->isProduction ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    public function apiBaseUrl(): string
    {
        return $this->isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }

    public function isConfigured(): bool
    {
        return $this->serverKey !== '';
    }
}
