<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    /**
     * Fields whose change must end the account's existing sessions (see App\Filters\Auth).
     */
    public const SESSION_SENSITIVE_FIELDS = ['password', 'access_level', 'account_status'];

    protected $table         = 'table_user';
    protected $primaryKey    = 'id_user';
    protected $allowedFields = [
        'username', 'email', 'password', 'registration_date', 'expired_date', 'access_level', 'reset_token', 'reset_expired_at',
        'account_status', 'session_version', 'preferred_locale',
    ];

    /**
     * Queues `session_version = session_version + 1` on the next update when $changes touches a
     * session-sensitive field, so revocation happens in the same UPDATE as the change itself.
     */
    public function revokingSessionsIf(array $changes): static
    {
        if (array_intersect(array_keys($changes), self::SESSION_SENSITIVE_FIELDS) !== []) {
            $this->set('session_version', 'session_version + 1', false);
        }

        return $this;
    }

    public function getUserByUsername($username)
    {
        return $this->where('username', $username)->first();
    }
}
