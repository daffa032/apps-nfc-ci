<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Libraries\MidtransClient;
use App\Repositories\PaymentOrderRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DomainException;

/**
 * Owner cancels an unpaid invoice, e.g. to choose another payment method (PRD BI-03). The order is
 * cancelled at Midtrans first, so its VA/QR can no longer be paid; only then is it closed locally.
 * If Midtrans says it is already paid (412), the latest status is applied instead and the owner is
 * told so. A late payment on a cancelled order would still be applied once (PaymentStatus::canMove).
 */
final class CancelPaymentService
{
    public const CANCELLED    = 'cancelled';
    public const ALREADY_PAID = 'already_paid';

    public function __construct(
        private ?PaymentOrderRepository $orders = null,
        private ?MidtransClient $gateway = null,
        private ?ApplyPaymentService $payments = null,
    ) {
        $this->orders ??= new PaymentOrderRepository();
        $this->gateway ??= new MidtransClient();
    }

    public function cancel(int $userId, string $orderCode, DateTimeInterface $now): string
    {
        $order = $this->orders->findOwnedOrder($orderCode, $userId);
        if ($order === null || ! in_array($order['status'], ['creating', 'pending', 'checking'], true)) {
            throw new DomainException(lang('Billing.cancelNotOpen'));
        }

        // Network errors propagate: nothing is closed locally unless Midtrans answered.
        $code = $this->gateway->cancel((string) $order['order_code']);
        if ($code === '412') {
            // Midtrans refused: most likely paid meanwhile. Take its word for the current status.
            ($this->payments ??= new ApplyPaymentService($this->orders, gateway: $this->gateway))->refresh($order, $now);
            $fresh = $this->orders->findByCode((string) $order['order_code']);
            if ($fresh !== null && $fresh['status'] === PaymentStatus::PAID) {
                return self::ALREADY_PAID;
            }

            throw new DomainException(lang('Billing.cancelRefused'));
        }

        $nowUtc = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $this->orders->withLockedOrder((int) $order['id'], static function (array $locked) use ($nowUtc): array {
            // A notification may have marked it paid while we were talking to Midtrans.
            return in_array($locked['status'], ['creating', 'pending', 'checking'], true)
                ? ['status' => PaymentStatus::CANCELLED, 'gateway_status' => 'cancel', 'last_checked_at' => $nowUtc]
                : [];
        });
        $fresh = $this->orders->findByCode((string) $order['order_code']);

        return $fresh !== null && $fresh['status'] === PaymentStatus::PAID ? self::ALREADY_PAID : self::CANCELLED;
    }
}
