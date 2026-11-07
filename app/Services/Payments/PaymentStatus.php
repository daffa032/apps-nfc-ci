<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Maps Midtrans transaction statuses to local order statuses (TECHNICAL "Status lokal").
 * Only settlement, or capture with fraud_status accept, grants access. Pure.
 */
final class PaymentStatus
{
    public const PENDING   = 'pending';
    public const CHECKING  = 'checking';
    public const PAID      = 'paid';
    public const FAILED    = 'failed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED   = 'expired';
    public const REFUNDED  = 'refunded';

    /**
     * Statuses that never change again (except a paid order being refunded).
     */
    public const FINAL = [self::PAID, self::FAILED, self::CANCELLED, self::EXPIRED, self::REFUNDED];

    public static function fromGateway(string $transactionStatus, ?string $fraudStatus): string
    {
        $fraud = $fraudStatus === null || $fraudStatus === '' ? 'accept' : strtolower($fraudStatus);

        return match (strtolower($transactionStatus)) {
            'settlement'                                                   => self::PAID,
            'capture'                                                      => $fraud === 'accept' ? self::PAID : self::CHECKING,
            'pending', 'authorize'                                         => self::PENDING,
            'deny', 'failure'                                              => self::FAILED,
            'cancel'                                                       => self::CANCELLED,
            'expire'                                                       => self::EXPIRED,
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => self::REFUNDED,
            default                                                        => self::CHECKING,
        };
    }

    /**
     * Whether a stored status may move to $next. A paid order never goes back to pending or
     * failed (late or out-of-order events); it can only become refunded.
     */
    public static function canMove(string $current, string $next): bool
    {
        if ($current === $next) {
            return false;
        }
        if ($current === self::PAID) {
            return $next === self::REFUNDED;
        }
        if ($current === self::REFUNDED) {
            return false;
        }
        // A late successful payment after expire/deny still counts (TECHNICAL).
        if (in_array($current, [self::FAILED, self::CANCELLED, self::EXPIRED], true)) {
            return $next === self::PAID;
        }

        return true;
    }
}
