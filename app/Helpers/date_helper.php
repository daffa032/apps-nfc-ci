<?php

declare(strict_types=1);

if (!function_exists('legacy_expiry_date')) {
    /**
     * Parses a legacy DATE expiry as a WIB calendar day, or null when it is missing or invalid.
     * 0000-00-00 and impossible dates never grant access (PRD section 5).
     */
    function legacy_expiry_date(?string $expiredDate): ?DateTimeImmutable
    {
        $value = substr(trim((string) $expiredDate), 0, 10);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Jakarta'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}

if (!function_exists('legacy_expiry_days_left')) {
    /**
     * Whole WIB days from today until the expiry day: 0 on the last day, negative once expired.
     */
    function legacy_expiry_days_left(DateTimeImmutable $expiry, DateTimeInterface $now): int
    {
        $today = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('Asia/Jakarta'))->setTime(0, 0);

        return (int) $today->diff($expiry)->format('%r%a');
    }
}

if (!function_exists('remaining_days_label')) {
    /**
     * Remaining activation label for a legacy DATE expiry.
     *
     * A date means active through the end of that day in WIB (PRD section 5), so the last day
     * reads "Berakhir hari ini" instead of expired. The `auth` filter still compares dates in the
     * app timezone (UTC); aligning it is part of LOG-04.
     */
    function remaining_days_label(?string $expiredDate, DateTimeInterface $now): string
    {
        if (trim((string) $expiredDate) === '') {
            return lang('Date.noExpiry');
        }

        $expiry = legacy_expiry_date($expiredDate);
        $days = $expiry === null ? -1 : legacy_expiry_days_left($expiry, $now);

        return match (true) {
            $days < 0 => lang('Date.expired'),
            $days === 0 => lang('Date.endsToday'),
            default => lang('Date.days', [$days]),
        };
    }
}

if (!function_exists('format_date_id')) {
    /**
     * Long date in WIB in the current language, e.g. "10 Oktober 2026" / "10 October 2026".
     */
    function format_date_id(DateTimeInterface $date): string
    {
        $months = explode(',', lang('Date.months'));
        $wib = DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone('Asia/Jakarta'));

        return $wib->format('j') . ' ' . $months[(int) $wib->format('n') - 1] . ' ' . $wib->format('Y');
    }
}

if (!function_exists('subscription_last_day')) {
    /**
     * Last covered WIB day of a subscription that ends at $endsAtUtc (exclusive), e.g.
     * '2026-10-10 17:00:00' UTC (= 11 Oct 00:00 WIB) -> 10 October 2026. Null when there is no end.
     */
    function subscription_last_day(?string $endsAtUtc): ?DateTimeImmutable
    {
        if ($endsAtUtc === null || trim($endsAtUtc) === '') {
            return null;
        }

        return (new DateTimeImmutable($endsAtUtc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Jakarta'))
            ->modify('-1 second')
            ->setTime(0, 0);
    }
}

if (!function_exists('format_datetime_wib')) {
    /**
     * UTC 'Y-m-d H:i:s' as "10 Okt 2026, 14.05 WIB" (month name in the current language).
     */
    function format_datetime_wib(string $utc): string
    {
        $months = explode(',', lang('Date.monthsShort'));
        $time = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'));

        return $time->format('j') . ' ' . $months[(int) $time->format('n') - 1] . ' ' . $time->format('Y, H.i') . ' WIB';
    }
}
