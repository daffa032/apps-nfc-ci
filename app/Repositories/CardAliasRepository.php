<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Previous usernames that still open a card (LOG-14). Writes run inside the caller's transaction
 * that renames the account, so the rename and the alias are saved together.
 */
class CardAliasRepository
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function userIdForAlias(string $alias): ?int
    {
        $row = $this->db->table('card_aliases')->select('user_id')->where('alias', $alias)->get()->getRowArray();

        return $row === null ? null : (int) $row['user_id'];
    }

    /**
     * Whether $alias still points to an account other than $exceptUserId.
     */
    public function takenByOther(string $alias, ?int $exceptUserId): bool
    {
        $query = $this->db->table('card_aliases')->where('alias', $alias);
        if ($exceptUserId !== null) {
            $query->where('user_id !=', $exceptUserId);
        }

        return $query->countAllResults() > 0;
    }

    /**
     * Keeps $oldUsername as an alias of $userId. Taking back one's own earlier username removes that
     * alias, because the name is now the live URL again.
     */
    public function rememberRename(int $userId, string $oldUsername, string $newUsername): void
    {
        $this->db->table('card_aliases')->where('user_id', $userId)->where('alias', $newUsername)->delete();
        $this->db->query(
            "INSERT IGNORE INTO {$this->db->prefixTable('card_aliases')} (alias, user_id, created_at) VALUES (?, ?, ?)",
            [$oldUsername, $userId, (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s')],
        );
    }
}
