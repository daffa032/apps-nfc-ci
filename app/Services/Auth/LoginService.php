<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\UserModel;
use App\Services\Users\AccountStatus;
use DomainException;

/**
 * Checks credentials and returns the session values for a successful sign-in.
 * Messages keep the existing login page wording (now in Indonesian); the page itself is unchanged.
 */
final class LoginService
{
    public function __construct(private ?UserModel $users = null)
    {
        $this->users ??= new UserModel();
    }

    /**
     * @return array{id_user: int, username: string, access_level: string, session_version: int, logged_in: true}
     */
    public function attempt(string $username, string $password): array
    {
        $user = $username !== '' ? $this->users->getUserByUsername($username) : null;
        if (! $user) {
            throw new DomainException(lang('Auth.errUsername'));
        }
        if (! password_verify($password, (string) $user['password'])) {
            throw new DomainException(lang('Auth.errPassword'));
        }
        // Checked only after the password, so this message is never shown to someone guessing.
        if (AccountStatus::isSuspended($user)) {
            throw new DomainException(lang('Auth.errSuspended'));
        }

        // An expired User may still sign in to reach billing and settings (PRD EX-01);
        // the `subscription` filter keeps card features closed until the card is active again.
        return [
            'id_user'         => (int) $user['id_user'],
            'username'        => (string) $user['username'],
            'access_level'    => (string) $user['access_level'],
            'session_version' => AccountStatus::sessionVersion($user),
            'logged_in'       => true,
        ];
    }
}
