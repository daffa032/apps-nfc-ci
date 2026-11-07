<?php

declare(strict_types=1);

use App\Repositories\SubscriptionRepository;

/**
 * In-memory subscription periods for unit tests: [userId => [[startsAtUtc, endsAtUtc], ...]].
 */
class PeriodListRepository extends SubscriptionRepository
{
    public function __construct(private array $periods)
    {
    }

    public function currentForUser(int $userId, string $nowUtc): ?array
    {
        foreach ($this->periods[$userId] ?? [] as [$start, $end]) {
            if ($start <= $nowUtc && $nowUtc < $end) {
                return ['starts_at' => $start, 'ends_at' => $end, 'plan_code' => 'basic', 'plan_label' => 'Basic'];
            }
        }

        return null;
    }

    public function latestForUser(int $userId): ?array
    {
        $rows = $this->periods[$userId] ?? [];

        return $rows === [] ? null : ['starts_at' => end($rows)[0], 'ends_at' => end($rows)[1], 'plan_code' => 'basic', 'plan_label' => 'Basic'];
    }
}
