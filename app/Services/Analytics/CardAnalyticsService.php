<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Repositories\CardAnalyticsRepository;
use App\Services\Cards\SubscriptionAccessService;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Pro visit statistics (PRD section 6, AN-01, AN-02).
 *
 * A view is a GET of an active card by a visitor who is not a recognised bot, not the owner and
 * not staff. Unique visitors are estimated per WIB day from an HMAC of network metadata keyed by a
 * secret that changes every day; the raw IP and user agent are never stored. Contact clicks are
 * clicks on WhatsApp/phone/email, not conversations. Counting only runs while the card is on Pro.
 * Analytics failures never break the card: every write is caught and logged.
 */
final class CardAnalyticsService
{
    public const EVENTS = ['whatsapp', 'phone', 'email'];

    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|skype|curl|wget|python|httpclient|headless|lighthouse|monitor/i';

    public function __construct(
        private ?CardAnalyticsRepository $metrics = null,
        private ?SubscriptionAccessService $access = null,
        private ?string $secret = null
    ) {
        $this->metrics ??= new CardAnalyticsRepository();
        $this->access ??= new SubscriptionAccessService();
        $this->secret ??= (string) config('Analytics')->secret;
    }

    public static function isBot(string $userAgent): bool
    {
        return trim($userAgent) === '' || preg_match(self::BOTS, $userAgent) === 1;
    }

    public static function wibDay(DateTimeInterface $now): string
    {
        return DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
    }

    public function isPro(array $account, DateTimeInterface $now): bool
    {
        return $this->access->planCode($account, $now) === 'pro';
    }

    /**
     * @param array{method: string, ip: string, user_agent: string, viewer_id: ?int, viewer_role: ?string} $visit
     */
    public function recordView(array $card, array $visit, DateTimeInterface $now): bool
    {
        $cardId = (int) ($card['id_user'] ?? 0);
        if (
            $cardId <= 0
            || strtoupper($visit['method']) !== 'GET'
            || self::isBot($visit['user_agent'])
            || ($visit['viewer_id'] ?? null) === $cardId
            || in_array($visit['viewer_role'] ?? '', SubscriptionAccessService::STAFF_ROLES, true)
        ) {
            return false;
        }

        try {
            if (!$this->isPro($card, $now)) {
                return false;
            }
            $day = self::wibDay($now);
            $this->metrics->recordView($cardId, $day, $this->visitorHash($cardId, $day, $visit['ip'], $visit['user_agent']));

            return true;
        } catch (Throwable $exception) {
            log_message('error', 'Card analytics view failed: {message}', ['message' => $exception->getMessage()]);

            return false;
        }
    }

    /**
     * Short token printed on the card page so contact-click events can only be sent for that card
     * on that WIB day. Null when no secret is configured (clicks are then not counted).
     */
    public function eventToken(int $cardId, DateTimeInterface $now): ?string
    {
        if ($this->secret === '' || $cardId <= 0) {
            return null;
        }

        return substr(hash_hmac('sha256', 'event|' . $cardId . '|' . self::wibDay($now), $this->secret), 0, 32);
    }

    public function recordContactClick(array $card, string $type, string $token, string $userAgent, DateTimeInterface $now): bool
    {
        $cardId = (int) ($card['id_user'] ?? 0);
        $expected = $this->eventToken($cardId, $now);
        if ($expected === null || !in_array($type, self::EVENTS, true) || !hash_equals($expected, $token) || self::isBot($userAgent)) {
            return false;
        }

        try {
            if (!$this->isPro($card, $now)) {
                return false;
            }
            $this->metrics->recordContactClick($cardId, self::wibDay($now));

            return true;
        } catch (Throwable $exception) {
            log_message('error', 'Card analytics click failed: {message}', ['message' => $exception->getMessage()]);

            return false;
        }
    }

    /**
     * Every date of a WIB month. Dates after today are 0 and marked future; today is marked.
     *
     * @return array{month: string, label: string, as_of: string, days: list<array>, totals: array<string, int>, months: list<array{value: string, label: string}>}
     */
    public function month(int $cardId, ?string $month, DateTimeInterface $now): array
    {
        $months = self::selectableMonths($now);
        $values = array_column($months, 'value');
        $month = in_array($month, $values, true) ? $month : $values[0];

        $tz = new DateTimeZone('Asia/Jakarta');
        $first = new DateTimeImmutable($month . '-01', $tz);
        $next = $first->modify('first day of next month');
        $today = self::wibDay($now);
        $stored = $this->metrics->month($cardId, $first->format('Y-m-d'), $next->format('Y-m-d'));

        $days = [];
        $totals = ['views' => 0, 'unique_visitors' => 0, 'contact_clicks' => 0];
        for ($day = $first; $day < $next; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $isFuture = $date > $today;
            $counts = $isFuture ? [] : ($stored[$date] ?? []);
            $row = [
                'date' => $date,
                'day' => (int) $day->format('j'),
                'views' => (int) ($counts['views'] ?? 0),
                'unique_visitors' => (int) ($counts['unique_visitors'] ?? 0),
                'contact_clicks' => (int) ($counts['contact_clicks'] ?? 0),
                'is_today' => $date === $today,
                'is_future' => $isFuture,
            ];
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $row[$key];
            }
            $days[] = $row;
        }

        return [
            'month' => $month,
            'label' => $months[array_search($month, $values, true)]['label'],
            'as_of' => DateTimeImmutable::createFromInterface($now)->setTimezone($tz)->format(DATE_ATOM),
            'days' => $days,
            'totals' => $totals,
            'months' => $months,
        ];
    }

    /**
     * Current WIB month and the 11 before it (aggregates are kept 12 months), newest first.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function selectableMonths(DateTimeInterface $now): array
    {
        $names = explode(',', lang('Date.months'));
        $first = new DateTimeImmutable(self::wibDay($now) . ' 00:00', new DateTimeZone('Asia/Jakarta'));
        $first = $first->modify('first day of this month');
        $months = [];
        $count = max(1, (int) config('Analytics')->metricsRetentionMonths);
        for ($i = 0; $i < $count; $i++) {
            $month = $first->modify('-' . $i . ' month');
            $months[] = ['value' => $month->format('Y-m'), 'label' => $names[(int) $month->format('n') - 1] . ' ' . $month->format('Y')];
        }

        return $months;
    }

    /**
     * @return array{visitors: int, metrics: int}
     */
    public function purge(DateTimeInterface $now): array
    {
        $config = config('Analytics');
        $today = new DateTimeImmutable(self::wibDay($now));

        return $this->metrics->purge(
            $today->modify('-' . max(1, $config->visitorRetentionDays) . ' days')->format('Y-m-d'),
            $today->modify('first day of this month')->modify('-' . (max(1, $config->metricsRetentionMonths) - 1) . ' months')->format('Y-m-d')
        );
    }

    /**
     * Daily key = HMAC(secret, day); visitor = HMAC(daily key, card|ip|ua). Rotating the key per day
     * means hashes from different days cannot be linked, and old hashes are purged after 30 days.
     */
    private function visitorHash(int $cardId, string $day, string $ip, string $userAgent): ?string
    {
        if ($this->secret === '') {
            return null;
        }
        $dailyKey = hash_hmac('sha256', 'visitor|' . $day, $this->secret, true);

        return hash_hmac('sha256', $cardId . '|' . $ip . '|' . $userAgent, $dailyKey);
    }
}
