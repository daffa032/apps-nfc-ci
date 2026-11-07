<?php

declare(strict_types=1);

use App\Libraries\MidtransClient;
use Config\Midtrans;
use PHPUnit\Framework\TestCase;

/**
 * Notification authenticity (TECHNICAL step 4): SHA512(order_id + status_code + gross_amount + ServerKey)
 * over the exact strings received.
 */
final class MidtransSignatureTest extends TestCase
{
    private function client(string $key = 'SB-Mid-server-test'): MidtransClient
    {
        $config = new Midtrans();
        $config->serverKey = $key;

        return new MidtransClient($config);
    }

    private function signed(array $payload, string $key = 'SB-Mid-server-test'): array
    {
        return $payload + ['signature_key' => hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $key)];
    }

    public function testValidSignatureIsAccepted(): void
    {
        $payload = $this->signed(['order_id' => 'NFC-1', 'status_code' => '200', 'gross_amount' => '199000.00']);
        self::assertTrue($this->client()->validSignature($payload));
        // Upper-case hex from a proxy is still the same signature.
        $payload['signature_key'] = strtoupper($payload['signature_key']);
        self::assertTrue($this->client()->validSignature($payload));
    }

    public function testAnyChangedFieldOrOtherKeyIsRejected(): void
    {
        $payload = $this->signed(['order_id' => 'NFC-1', 'status_code' => '200', 'gross_amount' => '199000.00']);
        foreach (['order_id' => 'NFC-2', 'status_code' => '201', 'gross_amount' => '199000'] as $field => $value) {
            self::assertFalse($this->client()->validSignature([$field => $value] + $payload), $field);
        }
        self::assertFalse($this->client('SB-Mid-server-other')->validSignature($payload));
    }

    public function testMissingFieldsOrNoServerKeyAreRejected(): void
    {
        $payload = $this->signed(['order_id' => 'NFC-1', 'status_code' => '200', 'gross_amount' => '199000.00']);
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            $broken = $payload;
            unset($broken[$field]);
            self::assertFalse($this->client()->validSignature($broken), $field);
        }
        self::assertFalse($this->client()->validSignature(['order_id' => 123] + $payload), 'Non-string values are rejected.');

        // A signature made with an empty key must never pass on an unconfigured server.
        self::assertFalse($this->client('')->validSignature($this->signed(['order_id' => 'NFC-1', 'status_code' => '200', 'gross_amount' => '1'], '')));
    }
}
