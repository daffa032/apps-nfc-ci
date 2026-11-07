<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Exceptions\FieldException;
use App\Repositories\PlanRepository;
use DomainException;

/**
 * Master Harga Paket (PRD MA-01). Prices are whole rupiah; durations are whole months. A disabled
 * plan is not offered for new orders, but existing subscriptions keep running.
 */
final class PlanAdminService
{
    public const DURATIONS = [1, 3, 6, 12, 24];

    public function __construct(private ?PlanRepository $plans = null)
    {
        $this->plans ??= new PlanRepository();
    }

    public function all(): array
    {
        return $this->plans->all();
    }

    /**
     * @return int the new version number
     */
    public function update(string $code, string $price, string $duration, bool $enabled, int $actorId): int
    {
        $digits = preg_replace('/[^\d]/', '', $price) ?? '';
        if ($price !== '' && ($digits === '' || strlen($digits) > 9)) {
            throw new FieldException('price_idr', lang('Master.errPrice'));
        }
        $priceIdr = $digits === '' ? null : (int) $digits;

        $months = $duration === '' ? null : (int) $duration;
        if ($months !== null && !in_array($months, self::DURATIONS, true)) {
            throw new FieldException('duration_months', lang('Master.errDuration'));
        }
        if ($enabled && ($priceIdr === null || $months === null)) {
            throw new FieldException($priceIdr === null ? 'price_idr' : 'duration_months', lang('Master.errEnableIncomplete'));
        }

        return $this->plans->saveVersion($code, $priceIdr, $months, $enabled, $actorId);
    }
}
