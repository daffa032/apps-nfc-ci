<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Libraries\MidtransClient;
use App\Repositories\AccountRepository;
use App\Repositories\PaymentOrderRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Audit\AuditLogService;
use App\Services\Cards\SubscriptionGrantService;
use App\Services\Users\AccountStatus;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DomainException;
use Throwable;

/**
 * Applies what the gateway reports to a local order (PRD BI-02, TECHNICAL "Midtrans"). The only
 * inputs that can grant access are a signed notification or the server's own status check; the
 * browser's return URL never does.
 *
 * In one transaction: account and order are locked, the event is stored once (fingerprint), the
 * status may only move forward, and a paid order adds exactly one subscription period
 * (order.subscription_id is unique). A renewal starts at the end of the latest period; a different
 * plan starts after it too (PRD section 3). A suspended account is marked paid for review only.
 *
 * A full refund or chargeback revokes the period this order added, in the same transaction, with a
 * required audit event (owner decision 27 Oct 2026). A partial refund/chargeback only flags the order
 * for review, because how much access to take back is a business decision.
 */
final class ApplyPaymentService
{
    public const OUTCOME_APPLIED       = 'applied';
    public const OUTCOME_UPDATED       = 'updated';
    public const OUTCOME_DUPLICATE     = 'duplicate';
    public const OUTCOME_IGNORED       = 'ignored';
    public const OUTCOME_REVIEW        = 'review';
    public const OUTCOME_UNKNOWN_ORDER = 'unknown_order';
    public const OUTCOME_REFUNDED      = 'refunded';

    public function __construct(
        private ?PaymentOrderRepository $orders = null,
        private ?SubscriptionRepository $subscriptions = null,
        private ?AccountRepository $accounts = null,
        private ?AuditLogService $audit = null,
        private ?MidtransClient $gateway = null,
    ) {
        $this->orders ??= new PaymentOrderRepository();
        $this->subscriptions ??= new SubscriptionRepository($this->orders->db());
        $this->accounts ??= new AccountRepository();
        $this->audit ??= new AuditLogService(null, $this->orders->db());
        $this->gateway ??= new MidtransClient();
    }

    /**
     * @param array<string, mixed> $report notification body or status response (strings as received)
     * @param string               $source 'notification' or 'status'
     */
    public function apply(array $report, string $source, DateTimeInterface $now): string
    {
        $orderCode         = (string) ($report['order_id'] ?? '');
        $transactionStatus = (string) ($report['transaction_status'] ?? '');
        $fraud             = isset($report['fraud_status']) ? (string) $report['fraud_status'] : null;
        $nowUtc            = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $event             = [
            'order_code'             => mb_substr($orderCode, 0, 50), 'source' => $source, 'transaction_status' => mb_substr($transactionStatus, 0, 32),
            'fraud_status'           => $fraud === null ? null : mb_substr($fraud, 0, 32), 'status_code' => mb_substr((string) ($report['status_code'] ?? ''), 0, 8),
            'gross_amount'           => mb_substr((string) ($report['gross_amount'] ?? ''), 0, 32), 'payment_type' => mb_substr((string) ($report['payment_type'] ?? ''), 0, 32) ?: null,
            'gateway_transaction_id' => mb_substr((string) ($report['transaction_id'] ?? ''), 0, 64) ?: null,
            'fingerprint'            => hash('sha256', implode('|', [$orderCode, $transactionStatus, $fraud ?? '', $report['status_code'] ?? '', $report['transaction_id'] ?? '', $report['settlement_time'] ?? $report['transaction_time'] ?? ''])),
            'received_at'            => $nowUtc,
        ];

        $order = $this->orders->findByCode($orderCode);
        if ($order === null) {
            // Kept briefly for investigation; never creates an account or access from a payload.
            $this->orders->recordEvent($event + ['order_id' => null, 'outcome' => self::OUTCOME_UNKNOWN_ORDER]);

            return self::OUTCOME_UNKNOWN_ORDER;
        }

        $outcome = self::OUTCOME_IGNORED;
        $this->orders->withLockedOrder((int) $order['id'], function (array $locked) use ($event, $report, $transactionStatus, $fraud, $nowUtc, &$outcome): array {
            $next          = PaymentStatus::fromGateway($transactionStatus, $fraud);
            $amountMatches = $this->amountMatches((string) ($report['gross_amount'] ?? ''), (int) $locked['amount_idr'])
                && strtoupper((string) ($report['currency'] ?? 'IDR')) === 'IDR';

            $fields = [];
            if (! $amountMatches) {
                $outcome = self::OUTCOME_REVIEW;
                $fields  = ['needs_review' => 1, 'gateway_status' => $event['transaction_status']];
            } elseif (PaymentStatus::canMove((string) $locked['status'], $next)) {
                $fields = ['status'          => $next, 'gateway_status' => $event['transaction_status'], 'payment_type' => $event['payment_type'],
                    'gateway_transaction_id' => $event['gateway_transaction_id'] ?? $locked['gateway_transaction_id']];
                $outcome = self::OUTCOME_UPDATED;
                if ($next === PaymentStatus::PAID) {
                    $fields['paid_at'] = $locked['paid_at'] ?? $nowUtc;
                }
                if ($next === PaymentStatus::REFUNDED) {
                    $outcome = $this->refund($locked, strtolower($transactionStatus), $nowUtc, $fields);
                }
            }

            $status = $fields['status'] ?? $locked['status'];
            if ($status === PaymentStatus::PAID && $locked['applied_at'] === null && $locked['subscription_id'] === null && $amountMatches) {
                $outcome = $this->grant($locked, $nowUtc, $fields);
            } elseif ($fields === [] && $locked['status'] === PaymentStatus::PAID && $next === PaymentStatus::PAID) {
                $outcome = self::OUTCOME_DUPLICATE;
            }

            $stored = $this->orders->recordEvent($event + ['order_id' => (int) $locked['id'], 'outcome' => $outcome]);
            if (! $stored && $outcome !== self::OUTCOME_APPLIED) {
                $outcome = self::OUTCOME_DUPLICATE;
            }

            return $fields + ['last_checked_at' => $nowUtc];
        });

        return $outcome;
    }

    /**
     * Super Admin resolution of an order flagged "needs review" (PRD AD-01/02). Always with a reason
     * and an audit event in the same transaction:
     * - grant:   a verified paid order (amount matches) gets its period now, through the same
     *            exactly-once path as a notification; refused while the account cannot receive one.
     * - revoke:  after a partial refund/chargeback, the order's period is revoked (history kept).
     * - dismiss: nothing changes except the flag (e.g. wrong amount handled outside the app).
     *
     * @throws DomainException with a message for the admin
     */
    public function resolve(string $orderCode, string $action, string $note, int $actorId, DateTimeInterface $now): void
    {
        $note = trim($note);
        if (! in_array($action, ['grant', 'revoke', 'dismiss'], true)) {
            throw new DomainException(lang('Billing.resolveBadAction'));
        }
        if (mb_strlen($note) < 5 || mb_strlen($note) > 255) {
            throw new DomainException(lang('Billing.resolveNoteRequired'));
        }
        $order = $this->orders->findByCode($orderCode);
        if ($order === null) {
            throw new DomainException(lang('Billing.orderMissing'));
        }
        $nowUtc = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $this->orders->withLockedOrder((int) $order['id'], function (array $locked) use ($action, $note, $actorId, $nowUtc): array {
            if ((int) $locked['needs_review'] !== 1) {
                throw new DomainException(lang('Billing.resolveNotFlagged'));
            }
            $fields = ['needs_review' => 0, 'reviewed_at' => $nowUtc, 'reviewed_by' => $actorId, 'review_action' => $action, 'review_note' => mb_substr($note, 0, 255)];

            if ($action === 'grant') {
                if ($locked['status'] !== PaymentStatus::PAID || $locked['applied_at'] !== null || $locked['subscription_id'] !== null) {
                    throw new DomainException(lang('Billing.resolveGrantNotPaid'));
                }
                if (! $this->paidAmountVerified($locked)) {
                    throw new DomainException(lang('Billing.resolveGrantAmount'));
                }
                $account = $this->accounts->find((int) $locked['user_id']);
                if (! $account || AccountStatus::isSuspended($account) || ($account['access_level'] ?? '') !== 'User') {
                    throw new DomainException(lang('Billing.resolveGrantAccount'));
                }
                $this->appendPaidPeriod($locked, $nowUtc, $fields, $actorId, 'review_grant');
            } elseif ($action === 'revoke') {
                if ($locked['subscription_id'] === null) {
                    throw new DomainException(lang('Billing.resolveRevokeNone'));
                }
                $this->subscriptions->revokePeriod((int) $locked['subscription_id'], (int) $locked['user_id'], $nowUtc);
            }

            $this->audit->appendRequired('payment.resolved', 'success', $actorId, 'payment_order', (string) $locked['order_code'], ['needs_review'], $action);

            return $fields;
        });
    }

    /**
     * A paid order's amount was verified when a stored gateway event for it reported the order's
     * exact amount with a paying status (the review flag may come from a different, mismatched event).
     */
    private function paidAmountVerified(array $order): bool
    {
        foreach ($this->orders->eventsFor((int) $order['id']) as $event) {
            if (PaymentStatus::fromGateway((string) $event['transaction_status'], $event['fraud_status']) === PaymentStatus::PAID
                && $this->amountMatches((string) $event['gross_amount'], (int) $order['amount_idr'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Asks the gateway for the order's current status and applies it (reconcile, "Periksa status").
     */
    public function refresh(array $order, DateTimeInterface $now): string
    {
        $nowUtc = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        try {
            $status = $this->gateway->status((string) $order['order_code']);
        } catch (Throwable $exception) {
            // Counted so reconcile backs off this order instead of retrying it first on every run.
            $this->orders->update((int) $order['id'], [
                'last_checked_at' => $nowUtc, 'check_failures' => (int) ($order['check_failures'] ?? 0) + 1, 'updated_at' => $nowUtc,
            ]);

            throw $exception;
        }
        if ((int) ($order['check_failures'] ?? 0) > 0) {
            $this->orders->update((int) $order['id'], ['check_failures' => 0]);
        }
        if ($status === null) {
            $fields = ['last_checked_at' => $nowUtc];
            $open   = in_array($order['status'], ['creating', 'pending', 'checking'], true);
            if ($open && ($order['snap_token'] ?? null) === null && ($order['redirect_url'] ?? null) === null) {
                // Snap was never created (gateway unreachable at checkout) and Midtrans has no
                // transaction with this order ID: nothing can be paid on it, so close it as failed and
                // let the owner start a new one. A late payment on this ID would still be applied.
                $fields['status'] = PaymentStatus::FAILED;
            } elseif ($open && $order['expires_at'] <= $nowUtc) {
                // No transaction at the gateway and the payment window has passed.
                $fields['status'] = PaymentStatus::EXPIRED;
            }
            $this->orders->update((int) $order['id'], $fields + ['updated_at' => $nowUtc]);

            return self::OUTCOME_IGNORED;
        }

        return $this->apply($status, 'status', $now);
    }

    /**
     * Full refund/chargeback: the order's period is revoked now (row kept as history) and any open
     * review is closed, since there is nothing left to decide. Partial: flagged for a Super Admin.
     */
    private function refund(array $order, string $gatewayStatus, string $nowUtc, array &$fields): string
    {
        if (str_starts_with($gatewayStatus, 'partial_')) {
            $fields['needs_review'] = 1;

            return self::OUTCOME_REVIEW;
        }
        if ($order['subscription_id'] !== null) {
            $this->subscriptions->revokePeriod((int) $order['subscription_id'], (int) $order['user_id'], $nowUtc);
        }
        $fields['needs_review'] = 0;
        $this->audit->appendRequired('payment.refunded', 'success', null, 'payment_order', (string) $order['order_code'], ['subscription'], $gatewayStatus, 'webhook');

        return self::OUTCOME_REFUNDED;
    }

    private function grant(array $order, string $nowUtc, array &$fields): string
    {
        $account = $this->accounts->find((int) $order['user_id']);
        if (! $account || AccountStatus::isSuspended($account) || ($account['access_level'] ?? '') !== 'User') {
            // Paid, but the card stays closed until an admin reviews it (TECHNICAL). This also covers
            // an account that became staff after checkout: no period can be added to it, and failing
            // here would make Midtrans retry the notification forever.
            $fields['needs_review'] = 1;

            return self::OUTCOME_REVIEW;
        }

        return $this->appendPaidPeriod($order, $nowUtc, $fields, null, 'webhook');
    }

    /**
     * Adds the order's period once (caller holds the account and order locks) with its required audit.
     * $actorId is null for the gateway, or the Super Admin resolving a review.
     */
    private function appendPaidPeriod(array $order, string $nowUtc, array &$fields, ?int $actorId, string $reason): string
    {
        $months = (int) $order['duration_months'];
        $period = $this->subscriptions->appendPeriod(
            (int) $order['user_id'],
            (int) $order['plan_id'],
            'payment',
            static fn (?string $latestEnd): array => SubscriptionGrantService::period($latestEnd, $nowUtc, $months),
            ['actor_id' => $actorId, 'kind' => 'payment', 'duration_months' => $months, 'note' => 'order ' . $order['order_code']],
        );
        $fields['applied_at']      = $nowUtc;
        $fields['subscription_id'] = (int) $period['id'];
        // Required audit in the same transaction: if it fails, the period is rolled back too.
        $this->audit->appendRequired('payment.applied', 'success', $actorId, 'payment_order', (string) $order['order_code'], ['subscription'], $reason === 'webhook' ? null : $reason, $actorId === null ? 'webhook' : 'user');

        return self::OUTCOME_APPLIED;
    }

    /**
     * Midtrans sends amounts like "199000.00": compare as whole rupiah.
     */
    private function amountMatches(string $gross, int $expected): bool
    {
        return preg_match('/\A\d+(?:\.0+)?\z/', $gross) === 1 && (int) $gross === $expected;
    }
}
