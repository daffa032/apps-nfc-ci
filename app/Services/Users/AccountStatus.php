<?php

declare(strict_types=1);

namespace App\Services\Users;

/**
 * Account status and session-version rules shared by login, the `auth` filter and the public card.
 *
 * Columns come from the 2026-10-11 migration. Until it runs, rows have no `account_status` or
 * `session_version`; they are treated as enabled at version 1 so the app keeps working.
 */
final class AccountStatus
{
    public const ENABLED   = 'enabled';
    public const SUSPENDED = 'suspended';

    public static function isSuspended(array $account): bool
    {
        return ($account['account_status'] ?? self::ENABLED) === self::SUSPENDED;
    }

    public static function sessionVersion(array $account): int
    {
        return max(1, (int) ($account['session_version'] ?? 1));
    }

    /**
     * Whether a session that recorded $sessionVersion at sign-in may continue for this account.
     */
    public static function sessionIsCurrent(array $account, mixed $sessionVersion): bool
    {
        return ! self::isSuspended($account) && (int) $sessionVersion === self::sessionVersion($account);
    }
}
