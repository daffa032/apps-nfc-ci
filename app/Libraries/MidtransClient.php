<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Midtrans;
use RuntimeException;

/**
 * HTTP client for Midtrans (Snap create + Core API status). Not a repository; no database access.
 * Errors never include the server key. A network timeout is reported as GatewayUnknownException so
 * the caller can mark the order "being checked" instead of creating another transaction.
 */
class MidtransClient
{
    public function __construct(private ?Midtrans $config = null)
    {
        $this->config ??= config('Midtrans');
    }

    /**
     * @param array<string, mixed> $payload Snap request body
     *
     * @return array{token: string, redirect_url: string}
     */
    public function createSnap(array $payload): array
    {
        [$status, $body] = $this->request('POST', $this->config->snapUrl(), $payload);
        if ($status !== 201 || ! is_string($body['token'] ?? null) || ! is_string($body['redirect_url'] ?? null)) {
            throw new RuntimeException('Snap request failed (HTTP ' . $status . ').');
        }

        return ['token' => $body['token'], 'redirect_url' => $body['redirect_url']];
    }

    /**
     * Current status of an order at the gateway, or null when the gateway has no transaction yet
     * (the buyer has not chosen a payment method).
     *
     * @return array<string, mixed>|null
     */
    public function status(string $orderCode): ?array
    {
        [$status, $body] = $this->request('GET', $this->config->apiBaseUrl() . '/v2/' . rawurlencode($orderCode) . '/status');
        $code            = (string) ($body['status_code'] ?? '');
        if ($code === '404') {
            return null;
        }
        if ($status !== 200 || ! isset($body['transaction_status'])) {
            throw new RuntimeException('Status request failed (HTTP ' . $status . ', code ' . $code . ').');
        }

        return $body;
    }

    /**
     * Cancels a pending transaction at the gateway (POST /v2/{order_id}/cancel). Returns the
     * gateway's status code: "200" cancelled, "404" no transaction yet (buyer never picked a
     * method), "412" not allowed (already paid/settled). Anything else throws.
     */
    public function cancel(string $orderCode): string
    {
        [$status, $body] = $this->request('POST', $this->config->apiBaseUrl() . '/v2/' . rawurlencode($orderCode) . '/cancel');
        $code            = (string) ($body['status_code'] ?? '');
        if (in_array($code, ['200', '404', '412'], true)) {
            return $code;
        }

        throw new RuntimeException('Cancel request failed (HTTP ' . $status . ', code ' . $code . ').');
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    protected function request(string $method, string $url, ?array $payload = null): array
    {
        if (! $this->config->isConfigured()) {
            throw new RuntimeException('Midtrans is not configured.');
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => $this->config->serverKey . ':',
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ] + ($payload === null ? [] : [CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR)]));
        $raw    = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $errno  = curl_errno($curl);
        curl_close($curl);
        if ($raw === false || $status === 0) {
            throw new GatewayUnknownException('Gateway not reachable (curl ' . $errno . ').');
        }
        $body = json_decode((string) $raw, true);

        return [$status, is_array($body) ? $body : []];
    }

    /**
     * Notification authenticity (Midtrans docs): SHA512(order_id + status_code + gross_amount + ServerKey),
     * computed on the exact strings received, compared in constant time.
     */
    public function validSignature(array $notification): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! is_string($notification[$field] ?? null) || $notification[$field] === '') {
                return false;
            }
        }
        $expected = hash('sha512', $notification['order_id'] . $notification['status_code'] . $notification['gross_amount'] . $this->config->serverKey);

        return $this->config->isConfigured() && hash_equals($expected, strtolower($notification['signature_key']));
    }
}
