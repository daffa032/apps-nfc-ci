<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Reads the account the `auth` filter has already verified for this request.
 * Ownership of any target record is still checked by the service.
 */
trait ResolvesCurrentUser
{
    protected function currentUserId(): int
    {
        return (int) session()->get('id_user');
    }

    protected function currentUsername(): string
    {
        return (string) session()->get('username');
    }

    protected function currentRole(): string
    {
        return (string) session()->get('access_level');
    }

    /**
     * Values the dashboard layout needs on every page.
     *
     * @return array{username: string, accessLevel: string, encryptedId: string, cardActive: bool, currentPath: string, locale: string}
     */
    protected function dashboardShellData(): array
    {
        return [
            'username' => $this->currentUsername(),
            'accessLevel' => $this->currentRole(),
            'encryptedId' => rtrim(strtr(base64_encode((string) $this->currentUserId()), '+/', '-_'), '='),
            // Set by the `auth` filter from SubscriptionAccessService for this request.
            'cardActive' => session()->get('card_active') !== false,
            // Route path without base URL (e.g. "dashboard/templates"), for the active sidebar item.
            'currentPath' => trim(uri_string(), '/'),
            // Interface language set by LocaleFilter, for <html lang> and the language dropdown.
            'locale' => service('request')->getLocale(),
        ];
    }
}
