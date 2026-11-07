<?php

declare(strict_types=1);

use App\Libraries\MidtransClient;
use App\Services\Payments\PaymentStatus;
use Config\Midtrans;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    public function testOnlySettlementOrAcceptedCaptureIsPaid(): void
    {
        self::assertSame('paid', PaymentStatus::fromGateway('settlement', null));
        self::assertSame('paid', PaymentStatus::fromGateway('capture', 'accept'));
        self::assertSame('checking', PaymentStatus::fromGateway('capture', 'challenge'));
        self::assertSame('pending', PaymentStatus::fromGateway('pending', null));
        self::assertSame('failed', PaymentStatus::fromGateway('deny', null));
        self::assertSame('cancelled', PaymentStatus::fromGateway('cancel', null));
        self::assertSame('expired', PaymentStatus::fromGateway('expire', null));
        self::assertSame('refunded', PaymentStatus::fromGateway('partial_refund', null));
        self::assertSame('checking', PaymentStatus::fromGateway('something-new', null));
    }

    public function testPaidNeverGoesBackAndLatePaymentStillCounts(): void
    {
        self::assertFalse(PaymentStatus::canMove('paid', 'pending'));
        self::assertFalse(PaymentStatus::canMove('paid', 'expired'));
        self::assertTrue(PaymentStatus::canMove('paid', 'refunded'));
        self::assertTrue(PaymentStatus::canMove('expired', 'paid'), 'A late successful payment after expire still counts.');
        self::assertFalse(PaymentStatus::canMove('expired', 'pending'));
        self::assertTrue(PaymentStatus::canMove('pending', 'paid'));
        self::assertFalse(PaymentStatus::canMove('pending', 'pending'));
    }

    public function testNotificationSignatureUsesTheExactStringsAndTheServerKey(): void
    {
        $config = new Midtrans();
        $config->serverKey = 'test-server-key';
        $client = new MidtransClient($config);
        $body = ['order_id' => 'NFC-1', 'status_code' => '200', 'gross_amount' => '199000.00'];
        $body['signature_key'] = hash('sha512', 'NFC-1' . '200' . '199000.00' . 'test-server-key');

        self::assertTrue($client->validSignature($body));
        self::assertFalse($client->validSignature(['gross_amount' => '199000'] + $body), 'The amount string must be exactly what was signed.');
        self::assertFalse($client->validSignature(['signature_key' => str_repeat('0', 128)] + $body));
        self::assertFalse($client->validSignature(array_diff_key($body, ['signature_key' => 1])));

        $config->serverKey = '';
        self::assertFalse((new MidtransClient($config))->validSignature($body), 'Without a key nothing is trusted.');
    }
}
