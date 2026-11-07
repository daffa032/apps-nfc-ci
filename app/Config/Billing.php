<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Manual renewal instructions shown on /dashboard/billing until a payment gateway exists.
 * Set real values in the deployment's private .env (billing.*), never in source or docs.
 */
class Billing extends BaseConfig
{
    /**
     * WhatsApp number for renewals, digits with country code, e.g. 6281234567890. Empty hides the button.
     */
    public string $whatsapp = '';

    /**
     * Email address for renewals. Empty hides the button.
     */
    public string $email = '';

    /**
     * Optional bank transfer instructions shown as plain text, one item per line.
     */
    public string $transferInstructions = '';
}
