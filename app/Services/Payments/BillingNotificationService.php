<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Repositories\AccountRepository;
use App\Repositories\PaymentOrderRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Mail\AppMailer;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Billing emails sent by cron (php spark billing:notify), on the shared email layout
 * (app/Views/emails/, AppMailer):
 * - a receipt (invoice) once per paid and applied order;
 * - a refund notice once per fully refunded order;
 * - a renewal reminder 7 days and 1 day before the card's last active day (WIB), once per period
 *   and kind, and only when no later period is already paid for.
 * Only the account's own email and data are used; nothing is sent to staff accounts. Each email is
 * written in the account's saved language (table_user.preferred_locale, set by the IND/ENG switch).
 */
final class BillingNotificationService
{
    /** Reminder kind => days before the last active day. */
    public const REMINDERS = ['h7' => 7, 'h1' => 1];

    public function __construct(
        private ?PaymentOrderRepository $orders = null,
        private ?SubscriptionRepository $subscriptions = null,
        private ?AccountRepository $accounts = null,
        private ?AppMailer $mailer = null
    ) {
        $this->orders ??= new PaymentOrderRepository();
        $this->subscriptions ??= new SubscriptionRepository($this->orders->db());
        $this->accounts ??= new AccountRepository();
        $this->mailer ??= new AppMailer();
    }

    /**
     * @return array{receipts: int, refunds: int, reminders: int, skipped: bool}
     */
    public function run(DateTimeInterface $now): array
    {
        if (!$this->mailer->isConfigured()) {
            return ['receipts' => 0, 'refunds' => 0, 'reminders' => 0, 'skipped' => true];
        }
        helper('date');

        return ['receipts' => $this->sendReceipts($now), 'refunds' => $this->sendRefundNotices($now), 'reminders' => $this->sendReminders($now), 'skipped' => false];
    }

    private function sendReceipts(DateTimeInterface $now): int
    {
        $nowUtc = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'));
        $sent = 0;
        foreach ($this->orders->receiptsToSend($nowUtc->modify('-7 days')->format('Y-m-d H:i:s'), 50) as $order) {
            $account = $this->accounts->find((int) $order['user_id']);
            if (!$account || ($account['access_level'] ?? '') !== 'User' || (string) ($account['email'] ?? '') === '') {
                // Nobody to send it to; mark it so it is not retried forever.
                $this->orders->claimNotice((int) $order['id'], 'receipt_sent_at', $nowUtc->format('Y-m-d H:i:s'));
                continue;
            }
            $period = $order['subscription_id'] ? $this->subscriptions->find((int) $order['subscription_id']) : null;
            $sent += AppMailer::inLocale($account['preferred_locale'] ?? null, function () use ($order, $account, $period, $nowUtc): int {
            $data = [
                'name' => $this->nameOf($account),
                'orderCode' => (string) $order['order_code'],
                'plan' => self::planLabel($order),
                'amount' => self::rupiah((int) $order['amount_idr']),
                'paidAt' => format_datetime_wib((string) $order['paid_at']),
                'method' => lang('Billing.method_' . (in_array($order['payment_type'], ['credit_card', 'qris', 'gopay', 'bank_transfer', 'echannel', 'shopeepay', 'cstore'], true) ? $order['payment_type'] : 'other')),
                'periodLabel' => $period ? format_date_id(subscription_last_day($period['ends_at'])) : '-',
                'receiptUrl' => base_url('dashboard/billing/receipt/' . rawurlencode((string) $order['order_code'])),
            ];
            return $this->sendOnce($order, 'receipt_sent_at', (string) $account['email'], lang('Email.receiptSubject', [(string) $order['order_code']]), 'payment_receipt', $data, $nowUtc) ? 1 : 0;
            });
        }

        return $sent;
    }

    private function sendRefundNotices(DateTimeInterface $now): int
    {
        $nowUtc = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'));
        $sent = 0;
        foreach ($this->orders->refundNoticesToSend($nowUtc->modify('-7 days')->format('Y-m-d H:i:s'), 50) as $order) {
            $account = $this->accounts->find((int) $order['user_id']);
            if (!$account || ($account['access_level'] ?? '') !== 'User' || (string) ($account['email'] ?? '') === '') {
                $this->orders->claimNotice((int) $order['id'], 'refund_notice_sent_at', $nowUtc->format('Y-m-d H:i:s'));
                continue;
            }
            $sent += AppMailer::inLocale($account['preferred_locale'] ?? null, function () use ($order, $account, $nowUtc): int {
            $data = [
                'name' => $this->nameOf($account),
                'orderCode' => (string) $order['order_code'],
                'plan' => self::planLabel($order),
                'amount' => self::rupiah((int) $order['amount_idr']),
                'refundedAt' => format_datetime_wib((string) $order['updated_at']),
                // The invoice itself (owner-only), not the generic billing page.
                'billingUrl' => base_url('dashboard/billing?order=' . rawurlencode((string) $order['order_code'])),
            ];
            return $this->sendOnce($order, 'refund_notice_sent_at', (string) $account['email'], lang('Email.refundSubject', [(string) $order['order_code']]), 'payment_refund', $data, $nowUtc) ? 1 : 0;
            });
        }

        return $sent;
    }

    /**
     * Claims the order's one-time email first (so overlapping runs never send twice), sends it, and
     * releases the claim when sending failed so the next run retries.
     */
    private function sendOnce(array $order, string $column, string $to, string $subject, string $view, array $data, DateTimeImmutable $nowUtc): bool
    {
        if (!$this->orders->claimNotice((int) $order['id'], $column, $nowUtc->format('Y-m-d H:i:s'))) {
            return false;
        }
        if ($this->mailer->send($to, $subject, $view, $data)) {
            return true;
        }
        $this->orders->claimNotice((int) $order['id'], $column, null);

        return false;
    }

    private function nameOf(array $account): string
    {
        return (string) $account['username'];
    }

    private static function planLabel(array $order): string
    {
        return ucfirst((string) $order['plan_code']) . ' · ' . lang('Master.monthsN', [(int) $order['duration_months']]);
    }

    private static function rupiah(int $amount): string
    {
        return 'Rp' . number_format($amount, 0, ',', '.');
    }

    private function sendReminders(DateTimeInterface $now): int
    {
        $wib = new DateTimeZone('Asia/Jakarta');
        $today = DateTimeImmutable::createFromInterface($now)->setTimezone($wib)->setTime(0, 0);
        $sent = 0;
        foreach (self::REMINDERS as $kind => $days) {
            // Periods whose last active WIB day is exactly $days from today: ends_at (exclusive) is
            // midnight WIB of the day after it.
            $endsFrom = $today->modify('+' . ($days + 1) . ' days')->setTimezone(new DateTimeZone('UTC'));
            $endsTo = $endsFrom->modify('+1 day');
            foreach ($this->subscriptions->endingBetween($endsFrom->format('Y-m-d H:i:s'), $endsTo->format('Y-m-d H:i:s'), $kind, 200) as $period) {
                $account = $this->accounts->find((int) $period['user_id']);
                if (!$account || ($account['access_level'] ?? '') !== 'User' || (string) ($account['email'] ?? '') === '' || ($account['account_status'] ?? 'enabled') !== 'enabled') {
                    continue;
                }
                if (!$this->subscriptions->claimReminder((int) $period['id'], $kind, gmdate('Y-m-d H:i:s'))) {
                    continue;
                }
                $ok = AppMailer::inLocale($account['preferred_locale'] ?? null, fn (): bool => $this->mailer->send(
                    (string) $account['email'],
                    lang($days === 1 ? 'Email.reminderSubjectTomorrow' : 'Email.reminderSubject', [$days]),
                    'renewal_reminder',
                    ['name' => $this->nameOf($account), 'lastDay' => format_date_id(subscription_last_day($period['ends_at'])), 'days' => $days, 'billingUrl' => base_url('dashboard/billing')]
                ));
                if ($ok) {
                    $sent++;
                } else {
                    $this->subscriptions->releaseReminder((int) $period['id'], $kind);
                }
            }
        }

        return $sent;
    }
}
