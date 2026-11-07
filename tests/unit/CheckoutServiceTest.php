<?php

declare(strict_types=1);

use App\Libraries\GatewayUnknownException;
use App\Libraries\MidtransClient;
use App\Repositories\AccountRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PaymentOrderRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Audit\AuditLogService;
use App\Services\Payments\ApplyPaymentService;
use App\Services\Payments\CheckoutService;
use Config\Midtrans;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CheckoutServiceTest extends TestCase
{
    private const NOW = '2026-10-13 05:00:00';

    public function testCreatesOneOrderWithServerPriceAndSendsOnlyUsername(): void
    {
        [$service, $orders, $gateway] = $this->service();
        $result                       = $service->checkout(21, 'pro', str_repeat('a', 32), $this->now());

        $this->assertSame('pending', $result['status']);
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v4/redirection/x', $result['redirect_url']);
        $this->assertSame(199000, $orders->rows[0]['amount_idr']);
        $this->assertSame(['first_name' => 'daffa'], $gateway->sent[0]['customer_details']);
        $this->assertSame(199000, $gateway->sent[0]['transaction_details']['gross_amount']);

        // Same request key again (double click): the same order, no second Snap call.
        $this->assertSame($result['order_code'], $service->checkout(21, 'pro', str_repeat('a', 32), $this->now())['order_code']);
        $this->assertCount(1, $gateway->sent);
    }

    public function testUnreachableGatewayIsRetriedOnceThenMarkedBeingChecked(): void
    {
        [$service, $orders, $gateway] = $this->service(failSnap: 2);
        $result                       = $service->checkout(21, 'basic', str_repeat('b', 32), $this->now());

        $this->assertSame('checking', $result['status']);
        $this->assertCount(2, $gateway->sent, 'One retry after a connection error.');
        $this->assertSame('checking', $orders->rows[0]['status']);
    }

    public function testOneTransientErrorStillOpensThePaymentPage(): void
    {
        [$service, $orders] = $this->service(failSnap: 1);
        $this->assertSame('pending', $service->checkout(21, 'basic', str_repeat('c', 32), $this->now())['status']);
        $this->assertSame('pending', $orders->rows[0]['status']);
    }

    public function testAnOrderThatNeverReachedMidtransIsClosedAndANewOneIsCreated(): void
    {
        [$service, $orders, $gateway] = $this->service(failSnap: 2);
        $first                        = $service->checkout(21, 'basic', str_repeat('d', 32), $this->now());
        $this->assertSame('checking', $first['status']);

        // Gateway is back; Midtrans has no transaction for the first order ID.
        $gateway->failSnap = 0;
        $second            = $service->checkout(21, 'basic', str_repeat('e', 32), $this->now());

        $this->assertNotSame($first['order_code'], $second['order_code']);
        $this->assertSame('pending', $second['status']);
        $this->assertSame('failed', $orders->rows[0]['status'], 'The stuck order is closed, not left "being checked".');
        $this->assertSame([$first['order_code']], $gateway->statusAsked);
    }

    public function testSwitchingPlanCancelsTheOpenInvoiceFirst(): void
    {
        [$service, $orders, $gateway] = $this->service();
        $basic                        = $service->checkout(21, 'basic', str_repeat('1', 32), $this->now());
        $pro                          = $service->checkout(21, 'pro', str_repeat('2', 32), $this->now());

        $this->assertNotSame($basic['order_code'], $pro['order_code']);
        $this->assertSame([$basic['order_code']], $gateway->cancelled, 'The Basic invoice is cancelled at Midtrans before the Pro one is made.');
        $this->assertSame('cancelled', $orders->rows[0]['status']);
        $this->assertSame('pending', $orders->rows[1]['status']);
    }

    public function testNewInvoiceIsRefusedWhileTheOldOneCannotBeClosed(): void
    {
        [$service, $orders, $gateway] = $this->service();
        $service->checkout(21, 'basic', str_repeat('3', 32), $this->now());
        $gateway->cancelAnswer = 'error';

        try {
            $service->checkout(21, 'pro', str_repeat('4', 32), $this->now());
            $this->fail('A second payable invoice must not be created.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString($orders->rows[0]['order_code'], $exception->getMessage());
        }
        $this->assertCount(1, $orders->rows);
        $this->assertSame('pending', $orders->rows[0]['status']);
    }

    public function testUnknownPlanAndBadRequestKeyAreRefused(): void
    {
        [$service] = $this->service();

        foreach ([['gold', str_repeat('f', 32)], ['pro', 'short']] as [$plan, $key]) {
            try {
                $service->checkout(21, $plan, $key, $this->now());
                $this->fail("{$plan}/{$key} must be refused");
            } catch (DomainException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW, new DateTimeZone('UTC'));
    }

    /**
     * @return array{CheckoutService, object, object}
     */
    private function service(int $failSnap = 0): array
    {
        $orders = new class () extends PaymentOrderRepository {
            public array $rows = [];

            public function __construct()
            {
            }

            public function findByRequestKey(int $userId, string $requestKey): ?array
            {
                foreach ($this->rows as $row) {
                    if ($row['user_id'] === $userId && $row['request_key'] === $requestKey) {
                        return $row;
                    }
                }

                return null;
            }

            public function findByCode(string $orderCode): ?array
            {
                foreach ($this->rows as $row) {
                    if ($row['order_code'] === $orderCode) {
                        return $row;
                    }
                }

                return null;
            }

            public function openOrderFor(int $userId, string $nowUtc): ?array
            {
                $open = $this->waitingForUser($userId, $nowUtc);

                return $open === [] ? null : $open[0];
            }

            public function waitingForUser(int $userId, string $nowUtc): array
            {
                $open = array_filter($this->rows, static fn (array $r): bool => $r['user_id'] === $userId && in_array($r['status'], ['creating', 'pending', 'checking'], true) && $r['expires_at'] > $nowUtc);

                return array_reverse(array_values($open));
            }

            public function findOwnedOrder(string $orderCode, int $userId): ?array
            {
                $row = $this->findByCode($orderCode);

                return $row !== null && $row['user_id'] === $userId ? $row : null;
            }

            public function withLockedOrder(int $orderId, callable $change): array
            {
                $fields = $change($this->rows[$orderId - 1]);
                $this->update($orderId, $fields);

                return $this->rows[$orderId - 1];
            }

            public function insertOrder(array $row): int
            {
                $row['id'] = count($this->rows) + 1;
                $row += ['snap_token' => null, 'redirect_url' => null];
                $this->rows[] = $row;

                return $row['id'];
            }

            public function update(int $orderId, array $fields): void
            {
                $this->rows[$orderId - 1] = $fields + $this->rows[$orderId - 1];
            }
        };
        $plans = new class () extends PlanRepository {
            public function __construct()
            {
            }

            public function all(): array
            {
                return [
                    ['id' => 1, 'code' => 'basic', 'label' => 'Basic', 'enabled' => 1, 'price_idr' => 99000, 'duration_months' => 12, 'version' => 2],
                    ['id' => 2, 'code' => 'pro', 'label' => 'Pro', 'enabled' => 1, 'price_idr' => 199000, 'duration_months' => 12, 'version' => 2],
                ];
            }
        };
        $accounts = new class () extends AccountRepository {
            public function __construct()
            {
            }

            public function find(int $id): ?array
            {
                return $id === 21 ? ['id_user' => 21, 'username' => 'daffa', 'access_level' => 'User', 'account_status' => 'enabled'] : null;
            }
        };
        $config            = new Midtrans();
        $config->serverKey = 'test-key';
        $gateway           = new class ($config, $failSnap) extends MidtransClient {
            public array $sent        = [];
            public array $statusAsked = [];
            public array $cancelled   = [];

            /**
             * Gateway answer to a cancel: '200', '412', or 'error' to simulate an outage.
             */
            public string $cancelAnswer = '200';

            public function __construct(Midtrans $config, public int $failSnap)
            {
                parent::__construct($config);
            }

            public function createSnap(array $payload): array
            {
                $this->sent[] = $payload;
                if ($this->failSnap > 0) {
                    $this->failSnap--;

                    throw new GatewayUnknownException('Gateway not reachable (curl 6).');
                }

                return ['token' => 'tok', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/x'];
            }

            public function status(string $orderCode): ?array
            {
                $this->statusAsked[] = $orderCode;

                return null;
            }

            public function cancel(string $orderCode): string
            {
                if ($this->cancelAnswer === 'error') {
                    throw new GatewayUnknownException('Gateway not reachable (curl 7).');
                }
                $this->cancelled[] = $orderCode;

                return $this->cancelAnswer;
            }
        };

        $payments = new ApplyPaymentService(
            $orders,
            new class () extends SubscriptionRepository {
                public function __construct()
                {
                }
            },
            $accounts,
            new AuditLogService(new class () extends AuditLogRepository {
                public function __construct()
                {
                }
            }),
            $gateway,
        );

        return [new CheckoutService($orders, $plans, $accounts, $gateway, $config, $payments), $orders, $gateway];
    }
}
