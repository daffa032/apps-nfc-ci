<?php

declare(strict_types=1);

use App\Libraries\MidtransClient;
use App\Services\Payments\PaymentStatus;
use Config\Midtrans;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PaymentStatusTest extends TestCase
{
    public function testOnlySettlementOrAcceptedCaptureIsPaid(): void
    {
        $this->assertSame('paid', PaymentStatus::fromGateway('settlement', null));
        $this->assertSame('paid', PaymentStatus::fromGateway('capture', 'accept'));
        $this->assertSame('checking', PaymentStatus::fromGateway('capture', 'challenge'));
        $this->assertSame('pending', PaymentStatus::fromGateway('pending', null));
        $this->assertSame('failed', PaymentStatus::fromGateway('deny', null));
        $this->assertSame('cancelled', PaymentStatus::fromGateway('cancel', null));
        $this->assertSame('expired', PaymentStatus::fromGateway('expire', null));
        $this->assertSame('refunded', PaymentStatus::fromGateway('partial_refund', null));
        $this->assertSame('checking', PaymentStatus::fromGateway('something-new', null));
    }

    public function testPaidNeverGoesBackAndLatePaymentStillCounts(): void
    {
        $this->assertFalse(PaymentStatus::canMove('paid', 'pending'));
        $this->assertFalse(PaymentStatus::canMove('paid', 'expired'));
        $this->assertTrue(PaymentStatus::canMove('paid', 'refunded'));
        $this->assertTrue(PaymentStatus::canMove('expired', 'paid'), 'A late successful payment after expire still counts.');
        $this->assertFalse(PaymentStatus::canMove('expired', 'pending'));
        $this->assertTrue(PaymentStatus::canMove('pending', 'paid'));
        $this->assertFalse(PaymentStatus::canMove('pending', 'pending'));
    }

    public function testNotificationSignatureUsesTheExactStringsAndTheServerKey(): void
    {
        $config                = new Midtrans();
        $config->serverKey     = 'test-server-key';
        $client                = new MidtransClient($config);
        $body                  = ['order_id' => 'NFC-1', 'status_code' => '200', 'gross_amount' => '199000.00'];
        $body['signature_key'] = hash('sha512', 'NFC-1200199000.00test-server-key');

        $this->assertTrue($client->validSignature($body));
        $this->assertFalse($client->validSignature(['gross_amount' => '199000'] + $body), 'The amount string must be exactly what was signed.');
        $this->assertFalse($client->validSignature(['signature_key' => str_repeat('0', 128)] + $body));
        $this->assertFalse($client->validSignature(array_diff_key($body, ['signature_key' => 1])));

        $config->serverKey = '';
        $this->assertFalse((new MidtransClient($config))->validSignature($body), 'Without a key nothing is trusted.');
    }
}
