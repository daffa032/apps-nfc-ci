<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\FieldException;
use App\Libraries\GatewayUnknownException;
use App\Libraries\MidtransClient;
use App\Repositories\AccountRepository;
use App\Repositories\PaymentOrderRepository;
use App\Repositories\PlanRepository;
use App\Services\Users\AccountStatus;
use Config\Midtrans;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DomainException;
use Throwable;

/**
 * Owner checkout (PRD BI-01, BI-03). The browser only sends the plan code and a request key;
 * the price, duration, account and status come from the server. The order is stored first with a
 * price snapshot, then Snap is called; a repeated request with the same key, or an open order for
 * the same plan and price, returns the existing order instead of creating a second transaction.
 * Any other open order is cancelled at Midtrans first, so an owner never has two invoices that can
 * both be paid; if that is not possible right now, checkout is refused (TECHNICAL step 2).
 */
final class CheckoutService
{
    public function __construct(
        private ?PaymentOrderRepository $orders = null,
        private ?PlanRepository $plans = null,
        private ?AccountRepository $accounts = null,
        private ?MidtransClient $gateway = null,
        private ?Midtrans $config = null,
        private ?ApplyPaymentService $payments = null,
        private ?CancelPaymentService $cancels = null,
    ) {
        $this->orders ??= new PaymentOrderRepository();
        $this->plans ??= new PlanRepository();
        $this->accounts ??= new AccountRepository();
        $this->gateway ??= new MidtransClient();
        $this->config ??= config('Midtrans');
    }

    /**
     * Plans the owner can buy now (enabled, with a price and duration).
     *
     * @return list<array{code: string, label: string, price_idr: int, duration_months: int, version: int}>
     */
    public function offers(): array
    {
        $offers = [];

        foreach ($this->plans->all() as $plan) {
            if ((int) $plan['enabled'] === 1 && $plan['price_idr'] !== null && (int) $plan['price_idr'] > 0 && (int) $plan['duration_months'] > 0) {
                $offers[] = [
                    'code'            => (string) $plan['code'], 'label' => (string) $plan['label'], 'price_idr' => (int) $plan['price_idr'],
                    'duration_months' => (int) $plan['duration_months'], 'version' => (int) $plan['version'],
                ];
            }
        }

        return $offers;
    }

    /**
     * @return array{order_code: string, redirect_url: ?string, status: string}
     */
    public function checkout(int $userId, string $planCode, string $requestKey, DateTimeInterface $now): array
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $requestKey) !== 1) {
            throw new DomainException(lang('Billing.checkoutExpired'));
        }
        $account = $this->accounts->find($userId);
        if (! $account || ($account['access_level'] ?? '') !== 'User') {
            throw new DomainException(lang('Billing.checkoutNotAllowed'));
        }
        if (AccountStatus::isSuspended($account)) {
            throw new DomainException(lang('Billing.checkoutSuspended'));
        }
        if (! $this->config->isConfigured()) {
            throw new DomainException(lang('Billing.gatewayOff'));
        }

        // Same request repeated (double click, refresh): return what was created the first time.
        $existing = $this->orders->findByRequestKey($userId, $requestKey);
        if ($existing) {
            return $this->resume($existing);
        }

        $offer = null;

        foreach ($this->offers() as $candidate) {
            if ($candidate['code'] === $planCode) {
                $offer = $candidate;
            }
        }
        if ($offer === null) {
            throw new FieldException('plan_code', lang('Billing.planUnavailable'));
        }

        $nowUtc = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'));
        $open   = $this->orders->openOrderFor($userId, $nowUtc->format('Y-m-d H:i:s'));
        if ($open && ($open['redirect_url'] ?? null) === null) {
            // An earlier attempt never reached Midtrans. Ask Midtrans about that order ID first
            // (never two live transactions); if it has none, the old order is closed and a new one made.
            $open = $this->settleUnreachable($open, $now);
        }
        if ($open && $open['plan_code'] === $offer['code'] && (int) $open['amount_idr'] === $offer['price_idr']) {
            return $this->resume($open);
        }
        $this->closeOpenOrders($userId, $nowUtc->format('Y-m-d H:i:s'), $now);

        $orderCode = $this->config->orderPrefix . '-' . $nowUtc->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
        $expiresAt = $nowUtc->add(new DateInterval('PT' . max(15, $this->config->expiryMinutes) . 'M'));
        $stamp     = $nowUtc->format('Y-m-d H:i:s');
        $orderId   = $this->orders->insertOrder([
            'order_code'   => $orderCode, 'user_id' => $userId, 'plan_id' => $this->planId($offer['code']), 'plan_code' => $offer['code'],
            'plan_version' => $offer['version'], 'duration_months' => $offer['duration_months'], 'amount_idr' => $offer['price_idr'],
            'currency'     => 'IDR', 'status' => 'creating', 'request_key' => $requestKey, 'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at'   => $stamp, 'updated_at' => $stamp,
        ]);

        // Outside any database lock: the gateway may be slow. A connection error (DNS, refused) is
        // retried once; Midtrans rejects a duplicate order_id, so a retry can never create two.
        $request = [
            'transaction_details' => ['order_id' => $orderCode, 'gross_amount' => $offer['price_idr']],
            'item_details'        => [[
                'id'       => $offer['code'] . '-' . $offer['duration_months'],
                'price'    => $offer['price_idr'],
                'quantity' => 1,
                'name'     => mb_substr('Paket ' . $offer['label'] . ' ' . $offer['duration_months'] . ' bulan', 0, 50),
            ]],
            // Only the username: no email, phone or name is sent to the gateway by default.
            'customer_details' => ['first_name' => mb_substr((string) $account['username'], 0, 50)],
            'expiry'           => [
                'start_time' => $nowUtc->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s O'),
                'unit'       => 'minutes',
                'duration'   => max(15, $this->config->expiryMinutes),
            ],
            'callbacks' => ['finish' => base_url('dashboard/billing?order=' . rawurlencode($orderCode))],
        ];

        try {
            try {
                $snap = $this->gateway->createSnap($request);
            } catch (GatewayUnknownException) {
                usleep(800000);
                $snap = $this->gateway->createSnap($request);
            }
        } catch (GatewayUnknownException $exception) {
            // Unknown, not failed: reconcile checks this order code before anything new is created.
            $this->orders->update($orderId, ['status' => PaymentStatus::CHECKING, 'updated_at' => $stamp]);
            log_message('warning', 'Snap timeout for {order}: {message}', ['order' => $orderCode, 'message' => $exception->getMessage()]);

            return ['order_code' => $orderCode, 'redirect_url' => null, 'status' => PaymentStatus::CHECKING];
        } catch (Throwable $exception) {
            $this->orders->update($orderId, ['status' => PaymentStatus::FAILED, 'updated_at' => $stamp]);
            log_message('error', 'Snap create failed for {order}: {message}', ['order' => $orderCode, 'message' => $exception->getMessage()]);

            throw new DomainException(lang('Billing.checkoutFailed'));
        }

        $this->orders->update($orderId, [
            'status' => PaymentStatus::PENDING, 'snap_token' => $snap['token'], 'redirect_url' => $snap['redirect_url'], 'updated_at' => $stamp,
        ]);

        return ['order_code' => $orderCode, 'redirect_url' => $snap['redirect_url'], 'status' => PaymentStatus::PENDING];
    }

    /**
     * Cancels every still-payable order of the owner before a new one is made (another plan, or the
     * price changed). An order Midtrans reports as paid meanwhile stops checkout with a clear
     * message; a gateway error refuses checkout rather than leaving two live invoices.
     */
    private function closeOpenOrders(int $userId, string $nowUtc, DateTimeInterface $now): void
    {
        $cancels = $this->cancels ??= new CancelPaymentService($this->orders, $this->gateway, $this->payments);

        foreach ($this->orders->waitingForUser($userId, $nowUtc) as $order) {
            try {
                $result = $cancels->cancel($userId, (string) $order['order_code'], $now);
            } catch (Throwable $exception) {
                log_message('warning', 'Could not close {order} before a new checkout: {message}', ['order' => $order['order_code'], 'message' => $exception->getMessage()]);

                throw new DomainException(lang('Billing.checkoutPreviousOpen', [$order['order_code']]));
            }
            if ($result === CancelPaymentService::ALREADY_PAID) {
                throw new DomainException(lang('Billing.checkoutPreviousPaid', [$order['order_code']]));
            }
        }
    }

    /**
     * Status check for an open order that has no payment page. Returns the order when it is still
     * open (or now paid elsewhere), or null when Midtrans has nothing for it and it was closed.
     */
    private function settleUnreachable(array $order, DateTimeInterface $now): ?array
    {
        try {
            ($this->payments ??= new ApplyPaymentService($this->orders, gateway: $this->gateway))->refresh($order, $now);
        } catch (Throwable $exception) {
            // Still unreachable: keep it "being checked" rather than risk two live transactions.
            log_message('warning', 'Status check before checkout failed for {order}: {message}', ['order' => $order['order_code'], 'message' => $exception->getMessage()]);

            return $order;
        }
        $fresh = $this->orders->findByCode((string) $order['order_code']);

        return $fresh !== null && in_array($fresh['status'], ['creating', 'pending', 'checking'], true) ? $fresh : null;
    }

    /**
     * @return array{order_code: string, redirect_url: ?string, status: string}
     */
    private function resume(array $order): array
    {
        return ['order_code' => (string) $order['order_code'], 'redirect_url' => $order['redirect_url'] ?: null, 'status' => (string) $order['status']];
    }

    private function planId(string $code): int
    {
        foreach ($this->plans->all() as $plan) {
            if ($plan['code'] === $code) {
                return (int) $plan['id'];
            }
        }

        throw new DomainException(lang('Billing.planUnavailable'));
    }
}
