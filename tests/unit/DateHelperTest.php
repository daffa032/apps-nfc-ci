<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DateHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        helper('date');
    }

    public function testLastDayIsStillActiveUntilMidnightWib(): void
    {
        // 2026-10-10 23:59:59 WIB = 16:59:59 UTC
        self::assertSame('Berakhir hari ini', remaining_days_label('2026-10-10', $this->utc('2026-10-10 16:59:59')));
        // 2026-10-11 00:00:00 WIB = 2026-10-10 17:00:00 UTC
        self::assertSame('Akun telah kedaluwarsa', remaining_days_label('2026-10-10', $this->utc('2026-10-10 17:00:00')));
    }

    public function testEarlyMorningWibCountsTheWibDate(): void
    {
        // 2026-10-10 01:00 WIB is still 2026-10-09 in UTC; the label must follow WIB.
        self::assertSame('1 hari', remaining_days_label('2026-10-11', $this->utc('2026-10-09 18:00:00')));
    }

    public function testCountsWholeDaysAcrossMonthAndLeapYear(): void
    {
        self::assertSame('2 hari', remaining_days_label('2028-03-01', $this->utc('2028-02-28 05:00:00')));
        self::assertSame('365 hari', remaining_days_label('2027-10-10', $this->utc('2026-10-10 05:00:00')));
    }

    public function testInvalidOrMissingDatesNeverReadAsActive(): void
    {
        $now = $this->utc('2026-10-10 05:00:00');
        self::assertSame('Akun telah kedaluwarsa', remaining_days_label('0000-00-00', $now));
        self::assertSame('Akun telah kedaluwarsa', remaining_days_label('2026-13-40', $now));
        self::assertSame('Akun telah kedaluwarsa', remaining_days_label('not a date', $now));
        self::assertSame('Tanggal kedaluwarsa tidak tersedia', remaining_days_label(null, $now));
        self::assertSame('Tanggal kedaluwarsa tidak tersedia', remaining_days_label('', $now));
    }

    public function testIndonesianLongDateUsesWib(): void
    {
        self::assertSame('10 Oktober 2026', format_date_id($this->utc('2026-10-10 05:00:00')));
        // 23:30 UTC on 31 December is already 1 January in WIB.
        self::assertSame('1 Januari 2027', format_date_id($this->utc('2026-12-31 23:30:00')));
    }

    public function testLegacyExpiryParsing(): void
    {
        self::assertSame('2026-10-10', legacy_expiry_date('2026-10-10')?->format('Y-m-d'));
        self::assertSame('2026-10-10', legacy_expiry_date('2026-10-10 00:00:00')?->format('Y-m-d'));
        self::assertNull(legacy_expiry_date('0000-00-00'));
        self::assertNull(legacy_expiry_date('2026-02-30'));
        self::assertNull(legacy_expiry_date(null));
    }

    private function utc(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }
}
