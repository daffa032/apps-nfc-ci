<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Exceptions\FieldException;

/**
 * Rules for a new or changed username, which is also the card URL /{username} (PRD section 8).
 * Unchanged usernames are never re-checked, so existing accounts keep working.
 */
final class UsernamePolicy
{
    /** First path segments the app uses or will use; compared case-insensitively. */
    public const RESERVED = [
        'admin', 'api', 'assets', 'billing', 'cards', 'checkout', 'dashboard', 'favicon.ico',
        'forgot-password', 'forgot_password', 'index.php', 'login', 'loginpost', 'logout',
        'midtrans', 'payments', 'robots.txt', 'uploads', 'webhooks',
    ];

    public static function assertAllowed(string $username): void
    {
        if (preg_match('/\A[A-Za-z0-9._-]{3,64}\z/', $username) !== 1 || trim($username, '.') === '') {
            throw new FieldException('username', lang('Accounts.usernameRules'));
        }
        if (in_array(strtolower($username), self::RESERVED, true)) {
            throw new FieldException('username', lang('Accounts.usernameReserved'));
        }
    }
}
