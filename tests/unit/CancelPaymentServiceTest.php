<?php

declare(strict_types=1);

use App\Libraries\GatewayUnknownException;
use App\Libraries\MidtransClient;
use App\Repositories\AccountRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PaymentOrderRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Audit\AuditLogService;
use App\Services\Payments\ApplyPaymentService;
use App\Services\Payments\CancelPaymentService;
use Config\Midtrans;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CancelPaymentServiceTest extends TestCase
{
    public function testCancelsAtMidtransFirstThenClosesLocally(): void
    {
        [$service, $orders, $gateway] = $this->service('200');
        $this->assertSame('cancelled', $service->cancel(21, 'NFC-C-1', $this->now()));
        $this->assertSame(['NFC-C-1'], $gateway->cancelled);
        $this->assertSame('cancelled', $orders->row['status']);
    }

    public function testNoTransactionYetAtMidtransIsStillCancelled(): void
    {
        // 404: the buyer opened Snap but never picked a method; nothing can be paid on it.
        [$service, $orders] = $this->service('404');
        $this->assertSame('cancelled', $service->cancel(21, 'NFC-C-1', $this->now()));
        $this->assertSame('cancelled', $orders->row['status']);
    }

    public function testRefusedByMidtransWhileStillUnpaidIsReportedAndKeptOpen(): void
    {
        // 412 and Midtrans still reports pending: tell the owner, change nothing.
        [$service, $orders] = $this->service('412');

        try {
            $service->cancel(21, 'NFC-C-1', $this->now());
            $this->fail('Must be refused');
        } catch (DomainException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        $this->assertSame('pending', $orders->row['status']);
    }

    public function testNetworkErrorLeavesTheInvoiceOpen(): void
    {
        [$service, $orders, $gateway] = $this->service('200');
        $gateway->down                = true;

        try {
            $service->cancel(21, 'NFC-C-1', $this->now());
            $this->fail('Must surface the error');
        } catch (GatewayUnknownException) {
        }
        $this->assertSame('pending', $orders->row['status'], 'Nothing is closed unless Midtrans answered.');
    }

    public function testOnlyTheOwnersOpenInvoiceCanBeCancelled(): void
    {
        [$service, $orders, $gateway] = $this->service('200');

        foreach ([[99, 'NFC-C-1'], [21, 'NFC-OTHER']] as [$user, $code]) {
            try {
                $service->cancel($user, $code, $this->now());
                $this->fail("{$user}/{$code} must be refused");
            } catch (DomainException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
        $orders->row['status'] = 'paid';
        $this->expectException(DomainException::class);
        $service->cancel(21, 'NFC-C-1', $this->now());
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-13 06:00:00', new DateTimeZone('UTC'));
    }

    /**
     * @return array{CancelPaymentService, object, object}
     */
    private function service(string $cancelCode): array
    {
        $orders = new class () extends PaymentOrderRepository {
            public array $row = ['id' => 1, 'order_code' => 'NFC-C-1', 'user_id' => 21, 'status' => 'pending', 'snap_token' => 't', 'redirect_url' => 'u', 'expires_at' => '2026-10-14 06:00:00', 'amount_idr' => 99000];

            public function __construct()
            {
            }

            public function findOwnedOrder(string $orderCode, int $userId): ?array
            {
                return $orderCode === $this->row['order_code'] && $userId === $this->row['user_id'] ? $this->row : null;
            }

            public function findByCode(string $orderCode): ?array
            {
                return $orderCode === $this->row['order_code'] ? $this->row : null;
            }

            public function withLockedOrder(int $orderId, callable $change): array
            {
                $this->row = $change($this->row) + $this->row;

                return $this->row;
            }

            public function update(int $orderId, array $fields): void
            {
                $this->row = $fields + $this->row;
            }

            public function recordEvent(array $event): bool
            {
                return true;
            }
        };
        $config            = new Midtrans();
        $config->serverKey = 'k';
        $gateway           = new class ($config, $cancelCode) extends MidtransClient {
            public array $cancelled    = [];
            public bool $down          = false;
            public string $statusReply = 'pending';

            public function __construct(Midtrans $config, private string $code)
            {
                parent::__construct($config);
            }

            public function cancel(string $orderCode): string
            {
                if ($this->down) {
                    throw new GatewayUnknownException('Gateway not reachable (curl 6).');
                }
                $this->cancelled[] = $orderCode;

                return $this->code;
            }

            public function status(string $orderCode): ?array
            {
                return ['order_id' => $orderCode, 'transaction_status' => $this->statusReply === 'settled' ? 'settlement' : 'pending', 'status_code' => '200', 'gross_amount' => '99000.00', 'currency' => 'IDR'];
            }
        };
        $payments = new ApplyPaymentService(
            $orders,
            new class () extends SubscriptionRepository {
                public function __construct()
                {
                }
            },
            new class () extends AccountRepository {
                public function __construct()
                {
                }
            },
            new AuditLogService(new class () extends AuditLogRepository {
                public function __construct()
                {
                }
            }),
            $gateway,
        );

        return [new CancelPaymentService($orders, $gateway, $payments), $orders, $gateway];
    }
}
