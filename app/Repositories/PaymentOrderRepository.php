<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Payment orders and gateway events (PRD section 9). Owner-scoped reads take the user ID; apply()
 * locks the account and order rows so a payment is turned into access exactly once, even when the
 * notification and a status check arrive together.
 */
class PaymentOrderRepository
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function db(): BaseConnection
    {
        return $this->db;
    }

    public function findOwnedOrder(string $orderCode, int $userId): ?array
    {
        return $this->db->table('payment_orders')->where('order_code', $orderCode)->where('user_id', $userId)->get(1)->getRowArray() ?: null;
    }

    public function findByCode(string $orderCode): ?array
    {
        return $this->db->table('payment_orders')->where('order_code', $orderCode)->get(1)->getRowArray() ?: null;
    }

    public function findByRequestKey(int $userId, string $requestKey): ?array
    {
        return $this->db->table('payment_orders')->where('user_id', $userId)->where('request_key', $requestKey)->get(1)->getRowArray() ?: null;
    }

    /**
     * The owner's latest order that is still waiting (pending/checking/creating) and not expired.
     */
    public function openOrderFor(int $userId, string $nowUtc): ?array
    {
        return $this->db->table('payment_orders')->where('user_id', $userId)
            ->whereIn('status', ['creating', 'pending', 'checking'])->where('expires_at >', $nowUtc)
            ->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    /**
     * The owner's paid orders, including later refunded ones, newest first (billing history).
     *
     * @return list<array>
     */
    public function paidForUser(int $userId, int $limit = 10): array
    {
        return $this->db->table('payment_orders')->where('user_id', $userId)->whereIn('status', ['paid', 'refunded'])
            ->orderBy('paid_at', 'DESC')->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    /**
     * The owner's orders still waiting for payment (not expired), newest first.
     *
     * @return list<array>
     */
    public function waitingForUser(int $userId, string $nowUtc): array
    {
        return $this->db->table('payment_orders')->where('user_id', $userId)
            ->whereIn('status', ['creating', 'pending', 'checking'])->where('expires_at >', $nowUtc)
            ->orderBy('id', 'DESC')->get()->getResultArray();
    }

    public function insertOrder(array $row): int
    {
        if (! $this->db->table('payment_orders')->insert($row)) {
            throw new RuntimeException('Tagihan gagal dibuat.');
        }

        return (int) $this->db->insertID();
    }

    public function update(int $orderId, array $fields): void
    {
        $this->db->table('payment_orders')->where('id', $orderId)->update($fields);
    }

    /**
     * Stores one gateway event; false when the same event (fingerprint) is already stored.
     */
    public function recordEvent(array $event): bool
    {
        $sql = 'INSERT IGNORE INTO ' . $this->db->prefixTable('payment_events')
            . ' (order_id, order_code, source, transaction_status, fraud_status, status_code, gross_amount, payment_type, gateway_transaction_id, fingerprint, outcome, received_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $this->db->query($sql, [
            $event['order_id'], $event['order_code'], $event['source'], $event['transaction_status'], $event['fraud_status'],
            $event['status_code'], $event['gross_amount'], $event['payment_type'], $event['gateway_transaction_id'],
            $event['fingerprint'], $event['outcome'], $event['received_at'],
        ]);

        return $this->db->affectedRows() === 1;
    }

    /**
     * Runs $change inside one transaction with the account row, then the order row, locked (lock
     * order: account → order, TECHNICAL). $change gets the locked order and may return fields to
     * update on it. Callers may add more writes (subscription, audit) through the same connection.
     *
     * @param callable(array): array<string, mixed> $change
     */
    public function withLockedOrder(int $orderId, callable $change): array
    {
        $this->db->transBegin();

        try {
            $order = $this->db->query('SELECT user_id FROM ' . $this->db->prefixTable('payment_orders') . ' WHERE id = ?', [$orderId])->getRowArray();
            if (! $order) {
                throw new DomainException('Order not found.');
            }
            $this->db->query('SELECT id_user FROM ' . $this->db->prefixTable('table_user') . ' WHERE id_user = ? FOR UPDATE', [$order['user_id']]);
            $locked = $this->db->query('SELECT * FROM ' . $this->db->prefixTable('payment_orders') . ' WHERE id = ? FOR UPDATE', [$orderId])->getRowArray();

            $fields = $change($locked);
            if ($fields !== []) {
                $this->db->table('payment_orders')->where('id', $orderId)->update($fields + ['updated_at' => gmdate('Y-m-d H:i:s')]);
            }
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Order update failed.');
            }
            $this->db->transCommit();

            return $fields + $locked;
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    /**
     * Orders that still need a gateway check (reconcile): waiting ones and paid-but-not-applied.
     * An order whose checks keep failing waits longer each time (5 min × 2^failures, capped at
     * 6 hours), so a few broken orders cannot take every slot of a run. Orders paid in the last 30
     * days are rechecked every 6 hours, so a refund is seen even if its notification was missed.
     * Least recently checked first.
     *
     * @return list<array>
     */
    public function needingReconcile(string $nowUtc, int $limit): array
    {
        $table = $this->db->prefixTable('payment_orders');

        return $this->db->query(
            "SELECT * FROM {$table}
             WHERE ((status IN ('creating', 'pending', 'checking') OR (status = 'paid' AND applied_at IS NULL AND needs_review = 0))
                    AND (last_checked_at IS NULL
                         OR last_checked_at < DATE_SUB(?, INTERVAL LEAST(300 * POW(2, LEAST(check_failures, 7)), 21600) SECOND)))
                OR (status = 'paid' AND applied_at IS NOT NULL AND paid_at > DATE_SUB(?, INTERVAL 30 DAY)
                    AND (last_checked_at IS NULL OR last_checked_at < DATE_SUB(?, INTERVAL 6 HOUR)))
             ORDER BY last_checked_at IS NOT NULL, last_checked_at ASC, id ASC
             LIMIT ?",
            [$nowUtc, $nowUtc, $nowUtc, $limit],
        )->getResultArray();
    }

    /**
     * Cheap "has anything changed" marker for the live payments page: newest order, latest order
     * change and newest gateway event. Three index lookups (primary keys and
     * payment_orders_updated_at), so its cost does not grow with the number of orders. Orders are
     * never deleted, so no count is needed.
     */
    public function changeMarker(): string
    {
        $row = $this->db->query(
            'SELECT (SELECT MAX(id) FROM ' . $this->db->prefixTable('payment_orders') . ') AS last_id,'
            . ' (SELECT MAX(updated_at) FROM ' . $this->db->prefixTable('payment_orders') . ') AS last_update,'
            . ' (SELECT MAX(id) FROM ' . $this->db->prefixTable('payment_events') . ') AS last_event',
        )->getRowArray() ?? [];

        return substr(hash('sha256', implode('|', [$row['last_id'] ?? 0, $row['last_update'] ?? '', $row['last_event'] ?? 0])), 0, 16);
    }

    /**
     * Waiting orders for the payments page's background sync: still open, least recently checked
     * first, never more than $limit, so one sync stays short.
     *
     * @return list<array>
     */
    public function openForLiveSync(string $nowUtc, int $limit): array
    {
        return $this->db->table('payment_orders')
            ->whereIn('status', ['creating', 'pending', 'checking'])
            ->groupStart()->where('last_checked_at', null)->orWhere('last_checked_at <', gmdate('Y-m-d H:i:s', strtotime($nowUtc . ' UTC') - 30))->groupEnd()
            ->orderBy('last_checked_at IS NOT NULL', '', false)->orderBy('last_checked_at', 'ASC')->orderBy('id', 'ASC')
            ->limit($limit)->get()->getResultArray();
    }

    /**
     * Orders that need a person (AD-01 alert): flagged for review, or still open long after their
     * payment window, or paid but not applied for over an hour.
     *
     * @return list<array>
     */
    public function needingAttention(string $nowUtc, int $limit = 20): array
    {
        return $this->db->table('payment_orders o')
            ->select('o.order_code, o.status, o.needs_review, o.amount_idr, o.created_at, u.username')
            ->join('table_user u', 'u.id_user = o.user_id', 'left')
            ->groupStart()
            ->where('o.needs_review', 1)
            ->orGroupStart()->whereIn('o.status', ['creating', 'pending', 'checking'])->where('o.expires_at <', gmdate('Y-m-d H:i:s', strtotime($nowUtc . ' UTC') - 3600))->groupEnd()
            ->orGroupStart()->where('o.status', 'paid')->where('o.applied_at', null)->where('o.paid_at <', gmdate('Y-m-d H:i:s', strtotime($nowUtc . ' UTC') - 3600))->groupEnd()
            ->groupEnd()
            ->orderBy('o.id', 'ASC')->limit($limit)->get()->getResultArray();
    }

    /**
     * Paid and applied orders whose confirmation email has not gone out yet (newest week only, so
     * turning email on later does not mail old customers).
     *
     * @return list<array>
     */
    public function receiptsToSend(string $sinceUtc, int $limit): array
    {
        return $this->db->table('payment_orders')
            ->where('status', 'paid')->where('applied_at IS NOT NULL', null, false)->where('receipt_sent_at', null)
            ->where('paid_at >=', $sinceUtc)->orderBy('id', 'ASC')->limit($limit)->get()->getResultArray();
    }

    /**
     * Fully refunded orders (period revoked, no open review) whose refund email has not gone out.
     *
     * @return list<array>
     */
    public function refundNoticesToSend(string $sinceUtc, int $limit): array
    {
        return $this->db->table('payment_orders')
            ->where('status', 'refunded')->where('needs_review', 0)->where('refund_notice_sent_at', null)
            ->where('updated_at >=', $sinceUtc)->orderBy('id', 'ASC')->limit($limit)->get()->getResultArray();
    }

    /**
     * Claims (or with null releases) the one-time email of an order: receipt_sent_at or
     * refund_notice_sent_at. Claiming returns false when another run already did.
     */
    public function claimNotice(int $orderId, string $column, ?string $nowUtc): bool
    {
        if (! in_array($column, ['receipt_sent_at', 'refund_notice_sent_at'], true)) {
            throw new InvalidArgumentException('Unknown notice column.');
        }
        $builder = $this->db->table('payment_orders')->where('id', $orderId);
        if ($nowUtc !== null) {
            $builder->where($column, null);
        }
        $builder->update([$column => $nowUtc]);

        return $this->db->affectedRows() === 1;
    }

    /**
     * Marks the receipt as sent; false when another run already did (only one email per order).
     */
    public function markReceiptSent(int $orderId, string $nowUtc): bool
    {
        return $this->claimNotice($orderId, 'receipt_sent_at', $nowUtc);
    }

    /**
     * Admin list with filters from an allowlist (AD-01). Never returns a query builder.
     *
     * @return array{rows: list<array>, total: int}
     */
    public function searchForAdmin(array $filters, int $page, int $perPage = 25): array
    {
        $builder = $this->db->table('payment_orders o')
            ->select('o.*, u.username')
            ->join('table_user u', 'u.id_user = o.user_id', 'left');
        if (($filters['status'] ?? '') !== '') {
            $builder->where('o.status', $filters['status']);
        }
        if (($filters['review'] ?? '') === '1') {
            $builder->where('o.needs_review', 1);
        }
        if (($filters['q'] ?? '') !== '') {
            $builder->groupStart()->like('o.order_code', $filters['q'])->orLike('u.username', $filters['q'])->groupEnd();
        }
        if (($filters['fromUtc'] ?? '') !== '') {
            $builder->where('o.created_at >=', $filters['fromUtc']);
        }
        if (($filters['toUtc'] ?? '') !== '') {
            $builder->where('o.created_at <', $filters['toUtc']);
        }
        $total = $builder->countAllResults(false);
        $rows  = $builder->orderBy('o.id', 'DESC')->limit($perPage, max(0, ($page - 1) * $perPage))->get()->getResultArray();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return list<array>
     */
    public function eventsFor(int $orderId): array
    {
        return $this->db->table('payment_events')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getResultArray();
    }

    /**
     * Totals for the admin summary: verified paid gross, waiting, needs review (AD-01).
     *
     * @return array{paidCount: int, paidGross: int, waiting: int, review: int}
     */
    public function summary(string $fromUtc, string $toUtc): array
    {
        $paid = $this->db->table('payment_orders')->select('COUNT(*) AS n, COALESCE(SUM(amount_idr), 0) AS gross')
            ->where('status', 'paid')->where('paid_at >=', $fromUtc)->where('paid_at <', $toUtc)->get()->getRowArray();

        return [
            'paidCount' => (int) ($paid['n'] ?? 0),
            'paidGross' => (int) ($paid['gross'] ?? 0),
            'waiting'   => $this->db->table('payment_orders')->whereIn('status', ['creating', 'pending', 'checking'])->countAllResults(),
            'review'    => $this->db->table('payment_orders')->where('needs_review', 1)->countAllResults(),
        ];
    }
}
