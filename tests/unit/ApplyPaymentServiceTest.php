<?php

declare(strict_types=1);

use App\Libraries\MidtransClient;
use App\Repositories\AccountRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PaymentOrderRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Audit\AuditLogService;
use App\Services\Payments\ApplyPaymentService;
use Config\Midtrans;
use PHPUnit\Framework\TestCase;

/**
 * Applying gateway reports and resolving reviews, with in-memory repositories. The database-level
 * guarantees (locks, unique subscription_id, rollback on audit failure) are covered by
 * tests/database/RepositoryIntegrationTest.php.
 */
final class ApplyPaymentServiceTest extends TestCase
{
    private const NOW = '2026-10-26 03:00:00';

    public function testSettlementWithMatchingAmountGrantsOnce(): void
    {
        [$service, $orders, $subs] = $this->service();
        self::assertSame('applied', $service->apply($this->report('settlement'), 'notification', $this->now()));
        self::assertSame('paid', $orders->row['status']);
        self::assertCount(1, $subs->appended);

        // The same notification again and a later "capture" do not add a second period.
        self::assertContains($service->apply($this->report('settlement'), 'notification', $this->now()), ['duplicate', 'ignored']);
        $service->apply($this->report('capture', fraud: 'accept', txn: 'txn-2'), 'status', $this->now());
        self::assertCount(1, $subs->appended);
    }

    public function testPendingAndChallengeNeverGrant(): void
    {
        [$service, $orders, $subs] = $this->service();
        $service->apply($this->report('pending', code: '201'), 'notification', $this->now());
        $service->apply($this->report('capture', fraud: 'challenge'), 'notification', $this->now());
        self::assertSame([], $subs->appended);
        self::assertNotSame('paid', $orders->row['status']);
    }

    public function testOutOfOrderPendingDoesNotDowngradePaid(): void
    {
        [$service, $orders] = $this->service();
        $service->apply($this->report('settlement'), 'notification', $this->now());
        $service->apply($this->report('pending', code: '201', txn: 'txn-late'), 'notification', $this->now());
        self::assertSame('paid', $orders->row['status']);
    }

    public function testWrongAmountOrCurrencyIsHeldForReview(): void
    {
        [$service, $orders, $subs] = $this->service();
        self::assertSame('review', $service->apply($this->report('settlement', amount: '1000.00'), 'notification', $this->now()));
        self::assertSame(1, $orders->row['needs_review']);
        self::assertSame([], $subs->appended);

        [$service, $orders, $subs] = $this->service();
        self::assertSame('review', $service->apply(['currency' => 'USD'] + $this->report('settlement'), 'notification', $this->now()));
        self::assertSame([], $subs->appended);
    }

    public function testSuspendedOrStaffAccountIsPaidButHeldWithoutError(): void
    {
        foreach ([['account_status' => 'suspended'], ['access_level' => 'Admin']] as $change) {
            [$service, $orders, $subs] = $this->service(account: $change);
            // No exception: an error here would make Midtrans retry the notification forever.
            self::assertSame('review', $service->apply($this->report('settlement'), 'notification', $this->now()), json_encode($change));
            self::assertSame(['paid', 1], [$orders->row['status'], $orders->row['needs_review']]);
            self::assertSame([], $subs->appended);
        }
    }

    public function testFullRefundOrChargebackRevokesAccessAutomatically(): void
    {
        foreach (['refund', 'chargeback'] as $status) {
            [$service, $orders, $subs] = $this->service();
            $service->apply($this->report('settlement'), 'notification', $this->now());
            self::assertSame('refunded', $service->apply($this->report($status, txn: 'txn-r'), 'notification', $this->now()), $status);
            self::assertSame(['refunded', 0], [$orders->row['status'], $orders->row['needs_review']], $status);
            self::assertSame([$orders->row['subscription_id']], $subs->revoked, $status);

            // The same refund reported again (notification + status check) revokes nothing more.
            $service->apply($this->report($status, txn: 'txn-r'), 'status', $this->now());
            self::assertCount(1, $subs->revoked, $status);
        }
    }

    public function testPartialRefundIsFlaggedForReview(): void
    {
        [$service, $orders, $subs] = $this->service();
        $service->apply($this->report('settlement'), 'notification', $this->now());
        self::assertSame('review', $service->apply($this->report('partial_refund', txn: 'txn-r'), 'notification', $this->now()));
        self::assertSame(['refunded', 1], [$orders->row['status'], $orders->row['needs_review']]);
        self::assertSame([], $subs->revoked, 'A partial refund needs a person to decide.');
    }

    public function testUnknownOrderNeverGrants(): void
    {
        [$service, , $subs] = $this->service();
        self::assertSame('unknown_order', $service->apply(['order_id' => 'NFC-NOPE'] + $this->report('settlement'), 'notification', $this->now()));
        self::assertSame([], $subs->appended);
    }

    public function testReviewGrantAfterTheAccountIsRestored(): void
    {
        [$service, $orders, $subs, $accounts] = $this->service(account: ['account_status' => 'suspended']);
        $service->apply($this->report('settlement'), 'notification', $this->now());

        // Still suspended: refused, nothing granted, the flag stays.
        $this->assertRefused(fn () => $service->resolve('NFC-A-1', 'grant', 'akun dipulihkan', 1, $this->now()));
        self::assertSame(1, $orders->row['needs_review']);

        $accounts->account['account_status'] = 'enabled';
        $service->resolve('NFC-A-1', 'grant', 'akun dipulihkan', 1, $this->now());
        self::assertCount(1, $subs->appended);
        self::assertSame(1, $subs->appended[0]['grant']['actor_id']);
        self::assertSame(['needs_review' => 0, 'review_action' => 'grant'], ['needs_review' => $orders->row['needs_review'], 'review_action' => $orders->row['review_action']]);
        self::assertNotNull($orders->row['applied_at']);

        // Already resolved: a second grant is refused.
        $this->assertRefused(fn () => $service->resolve('NFC-A-1', 'grant', 'sekali lagi', 1, $this->now()));
        self::assertCount(1, $subs->appended);
    }

    public function testReviewGrantNeedsAVerifiedAmount(): void
    {
        [$service, $orders, $subs] = $this->service();
        $service->apply($this->report('settlement', amount: '1000.00'), 'notification', $this->now());
        // Even if someone set the status by hand, a period needs a gateway report of the right amount.
        $orders->row['status'] = 'paid';
        $this->assertRefused(fn () => $service->resolve('NFC-A-1', 'grant', 'coba berikan', 1, $this->now()));
        self::assertSame([], $subs->appended);

        $service->resolve('NFC-A-1', 'dismiss', 'sudah dicek di Midtrans', 1, $this->now());
        self::assertSame(['needs_review' => 0, 'review_action' => 'dismiss'], ['needs_review' => $orders->row['needs_review'], 'review_action' => $orders->row['review_action']]);
    }

    public function testReviewRevokeAfterPartialRefund(): void
    {
        [$service, $orders, $subs] = $this->service();
        $service->apply($this->report('settlement'), 'notification', $this->now());
        $service->apply($this->report('partial_refund', txn: 'txn-r'), 'notification', $this->now());
        $service->resolve('NFC-A-1', 'revoke', 'dana dikembalikan', 1, $this->now());
        self::assertSame([$orders->row['subscription_id']], $subs->revoked);
        self::assertSame(0, $orders->row['needs_review']);
    }

    public function testReviewNeedsAReasonAndAValidAction(): void
    {
        [$service] = $this->service();
        $service->apply($this->report('settlement', amount: '1'), 'notification', $this->now());
        $this->assertRefused(fn () => $service->resolve('NFC-A-1', 'dismiss', 'ok', 1, $this->now()));
        $this->assertRefused(fn () => $service->resolve('NFC-A-1', 'markpaid', 'tidak ada aksi ini', 1, $this->now()));
    }

    public function testFailedStatusChecksAreCountedForBackoff(): void
    {
        [$service, $orders, , , $gateway] = $this->service();
        $gateway->fail = true;
        try {
            $service->refresh($orders->row, $this->now());
            self::fail('The gateway error must surface.');
        } catch (RuntimeException) {
        }
        self::assertSame(1, $orders->row['check_failures']);

        $gateway->fail = false;
        $service->refresh($orders->row, $this->now());
        self::assertSame(0, $orders->row['check_failures'], 'A successful check resets the counter.');
    }

    private function assertRefused(callable $call): void
    {
        try {
            $call();
            self::fail('Must be refused');
        } catch (DomainException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW, new DateTimeZone('UTC'));
    }

    private function report(string $status, string $amount = '199000.00', ?string $fraud = null, string $code = '200', string $txn = 'txn-1'): array
    {
        return ['order_id' => 'NFC-A-1', 'transaction_status' => $status, 'status_code' => $code, 'gross_amount' => $amount,
            'currency' => 'IDR', 'payment_type' => 'bank_transfer', 'transaction_id' => $txn, 'settlement_time' => '2026-10-26 10:00:00']
            + ($fraud === null ? [] : ['fraud_status' => $fraud]);
    }

    /**
     * @return array{ApplyPaymentService, object, object, object, object}
     */
    private function service(array $account = []): array
    {
        $orders = new class extends PaymentOrderRepository {
            public array $row = [
                'id' => 1, 'order_code' => 'NFC-A-1', 'user_id' => 21, 'plan_id' => 2, 'plan_code' => 'pro', 'duration_months' => 12,
                'amount_idr' => 199000, 'status' => 'pending', 'gateway_status' => null, 'payment_type' => null, 'gateway_transaction_id' => null,
                'snap_token' => 'tok', 'redirect_url' => 'https://app.sandbox.midtrans.com/x', 'expires_at' => '2026-10-27 03:00:00',
                'paid_at' => null, 'applied_at' => null, 'subscription_id' => null, 'needs_review' => 0, 'check_failures' => 0,
                'review_action' => null,
            ];
            public array $events = [];

            public function __construct()
            {
            }

            public function findByCode(string $orderCode): ?array
            {
                return $orderCode === $this->row['order_code'] ? $this->row : null;
            }

            public function update(int $orderId, array $fields): void
            {
                $this->row = $fields + $this->row;
            }

            public function recordEvent(array $event): bool
            {
                foreach ($this->events as $stored) {
                    if ($stored['fingerprint'] === $event['fingerprint']) {
                        return false;
                    }
                }
                $this->events[] = $event;

                return true;
            }

            public function eventsFor(int $orderId): array
            {
                return array_values(array_filter($this->events, static fn (array $e): bool => $e['order_id'] === $orderId));
            }

            public function withLockedOrder(int $orderId, callable $change): array
            {
                // Like the real one: a thrown exception leaves the row unchanged (rollback).
                $fields = $change($this->row);
                $this->row = $fields + $this->row;

                return $this->row;
            }

            public function db(): \CodeIgniter\Database\BaseConnection
            {
                throw new LogicException('The service must not query the database directly in this test.');
            }
        };
        $subs = new class extends SubscriptionRepository {
            public array $appended = [];
            public array $revoked = [];

            public function __construct()
            {
            }

            public function appendPeriod(int $userId, int $planId, string $source, callable $startFor, array $grant): array
            {
                [$starts, $ends] = $startFor(null);
                $this->appended[] = ['user' => $userId, 'plan' => $planId, 'grant' => $grant];

                return ['starts_at' => $starts, 'ends_at' => $ends, 'id' => count($this->appended)];
            }

            public function revokePeriod(int $subscriptionId, int $userId, string $nowUtc): void
            {
                $this->revoked[] = $subscriptionId;
            }
        };
        $accounts = new class ($account) extends AccountRepository {
            public array $account;

            public function __construct(array $change)
            {
                $this->account = $change + ['id_user' => 21, 'username' => 'daffa', 'access_level' => 'User', 'account_status' => 'enabled'];
            }

            public function find(int $id): ?array
            {
                return $id === 21 ? $this->account : null;
            }
        };
        // Required audit rows go through this stand-in connection's insert().
        $auditDb = new class extends CodeIgniter\Database\MySQLi\Connection {
            public array $rows = [];

            public function __construct()
            {
            }

            public function table($tableName)
            {
                $db = $this;

                return new class ($db) {
                    public function __construct(private object $db)
                    {
                    }

                    public function insert(array $row): bool
                    {
                        $this->db->rows[] = $row;

                        return true;
                    }
                };
            }
        };
        $audit = new AuditLogService(new class extends AuditLogRepository {
            public function __construct()
            {
            }
        }, $auditDb);
        $config = new Midtrans();
        $config->serverKey = 'test';
        $gateway = new class ($config) extends MidtransClient {
            public bool $fail = false;

            public function status(string $orderCode): ?array
            {
                if ($this->fail) {
                    throw new RuntimeException('Status request failed (HTTP 500, code 500).');
                }

                return null;
            }
        };

        return [new ApplyPaymentService($orders, $subs, $accounts, $audit, $gateway), $orders, $subs, $accounts, $gateway];
    }
}
