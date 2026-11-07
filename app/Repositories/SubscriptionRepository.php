<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Subscription periods per user. Times are UTC DATETIME, periods are [starts_at, ends_at).
 * Writes keep table_user.expired_date in step (WIB date of the last covered day) so an older
 * deployment that still reads that column sees the same answer.
 */
class SubscriptionRepository
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /**
     * The period covering $nowUtc, with its plan, or null.
     */
    public function currentForUser(int $userId, string $nowUtc): ?array
    {
        return $this->db->table('subscriptions s')
            ->select('s.*, p.code AS plan_code, p.label AS plan_label')
            ->join('plans p', 'p.id = s.plan_id')
            ->where('s.user_id', $userId)
            ->where('s.revoked_at', null)
            ->where('s.starts_at <=', $nowUtc)
            ->where('s.ends_at >', $nowUtc)
            ->orderBy('s.ends_at', 'DESC')
            ->get(1)->getRowArray() ?: null;
    }

    /**
     * The non-revoked period that ends last (current, future or already ended), with its plan, or null.
     */
    public function latestForUser(int $userId): ?array
    {
        return $this->db->table('subscriptions s')
            ->select('s.*, p.code AS plan_code, p.label AS plan_label')
            ->join('plans p', 'p.id = s.plan_id')
            ->where('s.user_id', $userId)
            ->where('s.revoked_at', null)
            ->orderBy('s.ends_at', 'DESC')
            ->get(1)->getRowArray() ?: null;
    }

    /**
     * Plan ID for a manual grant. A disabled plan only closes new orders (PRD MA-01), so grants may
     * still use it.
     */
    public function planIdByCode(string $code): ?int
    {
        $row = $this->db->table('plans')->select('id')->where('code', $code)->get(1)->getRow();

        return $row ? (int) $row->id : null;
    }

    /**
     * Adds a period after locking the user's row, so two grants cannot both start from the same end.
     * $startFor receives the latest current end (UTC string or null) and returns [startsAt, endsAt].
     *
     * @param callable(?string): array{0: string, 1: string} $startFor
     */
    public function appendPeriod(int $userId, int $planId, string $source, callable $startFor, array $grant): array
    {
        $this->db->transBegin();
        try {
            $user = $this->db->query(
                "SELECT id_user FROM {$this->db->prefixTable('table_user')} WHERE id_user = ? AND access_level = ? FOR UPDATE",
                [$userId, 'User']
            )->getRowArray();
            if (!$user) {
                throw new DomainException(lang('Accounts.userMissing'));
            }

            $latest = $this->db->table('subscriptions')->selectMax('ends_at')
                ->where('user_id', $userId)->where('revoked_at', null)->get()->getRow()->ends_at ?? null;
            [$startsAt, $endsAt] = $startFor($latest);

            $now = gmdate('Y-m-d H:i:s');
            if (!$this->db->table('subscriptions')->insert([
                'user_id' => $userId, 'plan_id' => $planId, 'starts_at' => $startsAt, 'ends_at' => $endsAt,
                'source' => $source, 'created_at' => $now,
            ])) {
                throw new RuntimeException('Masa aktif gagal disimpan.');
            }
            $subscriptionId = (int) $this->db->insertID();
            if (!$this->db->table('subscription_grants')->insert($grant + ['subscription_id' => $subscriptionId, 'created_at' => $now])) {
                throw new RuntimeException('Pemberian masa aktif gagal dicatat.');
            }
            $this->syncLegacyExpiry($userId);

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Masa aktif gagal disimpan.');
            }
            $this->db->transCommit();

            return ['id' => $subscriptionId, 'starts_at' => $startsAt, 'ends_at' => $endsAt];
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function find(int $subscriptionId): ?array
    {
        return $this->db->table('subscriptions')->where('id', $subscriptionId)->get(1)->getRowArray() ?: null;
    }

    /**
     * Live periods ending in [fromUtc, toUtc) that are each user's last one (no later paid or granted
     * period exists) and have no $kind reminder yet. Used for renewal reminders.
     *
     * @return list<array>
     */
    public function endingBetween(string $fromUtc, string $toUtc, string $kind, int $limit): array
    {
        $subs = $this->db->prefixTable('subscriptions');
        $reminders = $this->db->prefixTable('subscription_reminders');

        return $this->db->query(
            "SELECT s.* FROM {$subs} s
             WHERE s.revoked_at IS NULL AND s.ends_at >= ? AND s.ends_at < ?
               AND NOT EXISTS (SELECT 1 FROM {$subs} later WHERE later.user_id = s.user_id AND later.revoked_at IS NULL AND later.ends_at > s.ends_at)
               AND NOT EXISTS (SELECT 1 FROM {$reminders} r WHERE r.subscription_id = s.id AND r.kind = ?)
             ORDER BY s.id ASC LIMIT ?",
            [$fromUtc, $toUtc, $kind, $limit]
        )->getResultArray();
    }

    /**
     * Records that a reminder is being sent; false when it was already sent (unique per period/kind).
     */
    public function claimReminder(int $subscriptionId, string $kind, string $nowUtc): bool
    {
        $this->db->query(
            'INSERT IGNORE INTO ' . $this->db->prefixTable('subscription_reminders') . ' (subscription_id, kind, sent_at) VALUES (?, ?, ?)',
            [$subscriptionId, $kind, $nowUtc]
        );

        return $this->db->affectedRows() === 1;
    }

    /**
     * Undoes a claim when the email could not be sent, so the next run retries it.
     */
    public function releaseReminder(int $subscriptionId, string $kind): void
    {
        $this->db->table('subscription_reminders')->where('subscription_id', $subscriptionId)->where('kind', $kind)->delete();
    }

    /**
     * Revokes one period of a user (a refunded payment), keeping the row as history. Runs inside the
     * caller's transaction when one is open (payment resolution locks the account first).
     */
    public function revokePeriod(int $subscriptionId, int $userId, string $nowUtc): void
    {
        $this->db->transBegin();
        try {
            $this->db->query("SELECT id_user FROM {$this->db->prefixTable('table_user')} WHERE id_user = ? FOR UPDATE", [$userId]);
            $this->db->table('subscriptions')->where('id', $subscriptionId)->where('user_id', $userId)->where('revoked_at', null)
                ->update(['revoked_at' => $nowUtc]);
            $this->syncLegacyExpiry($userId);
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Masa aktif gagal dicabut.');
            }
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /**
     * Ends access now: revokes every period that has not ended yet.
     */
    public function revokeActive(int $userId, string $nowUtc): void
    {
        $this->db->transBegin();
        try {
            $this->db->query(
                "SELECT id_user FROM {$this->db->prefixTable('table_user')} WHERE id_user = ? FOR UPDATE",
                [$userId]
            );
            $this->db->table('subscriptions')
                ->where('user_id', $userId)->where('revoked_at', null)->where('ends_at >', $nowUtc)
                ->update(['revoked_at' => $nowUtc]);
            $this->syncLegacyExpiry($userId);
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Masa aktif gagal dicabut.');
            }
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /**
     * Writes the last covered WIB day of the latest period to table_user.expired_date,
     * or 0000-00-00 when there is none. Never read by this code; kept for rollback.
     */
    private function syncLegacyExpiry(int $userId): void
    {
        $latest = $this->db->table('subscriptions')->selectMax('ends_at')
            ->where('user_id', $userId)->where('revoked_at', null)->get()->getRow()->ends_at ?? null;

        $date = '0000-00-00';
        if ($latest !== null) {
            $date = (new \DateTimeImmutable($latest, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('Asia/Jakarta'))->modify('-1 second')->format('Y-m-d');
        }
        // Zero date needs a relaxed sql_mode for this one statement on strict servers.
        $this->db->query("SET @nfc_prev_mode = @@SESSION.sql_mode, SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '')");
        $this->db->query("UPDATE {$this->db->prefixTable('table_user')} SET expired_date = ? WHERE id_user = ?", [$date, $userId]);
        $this->db->query('SET SESSION sql_mode = @nfc_prev_mode');
    }
}
