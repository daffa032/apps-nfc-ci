<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DateHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        helper('date');
    }

    public function testLastDayIsStillActiveUntilMidnightWib(): void
    {
        // 2026-10-10 23:59:59 WIB = 16:59:59 UTC
        $this->assertSame('Berakhir hari ini', remaining_days_label('2026-10-10', $this->utc('2026-10-10 16:59:59')));
        // 2026-10-11 00:00:00 WIB = 2026-10-10 17:00:00 UTC
        $this->assertSame('Akun telah kedaluwarsa', remaining_days_label('2026-10-10', $this->utc('2026-10-10 17:00:00')));
    }

    public function testEarlyMorningWibCountsTheWibDate(): void
    {
        // 2026-10-10 01:00 WIB is still 2026-10-09 in UTC; the label must follow WIB.
        $this->assertSame('1 hari', remaining_days_label('2026-10-11', $this->utc('2026-10-09 18:00:00')));
    }

    public function testCountsWholeDaysAcrossMonthAndLeapYear(): void
    {
        $this->assertSame('2 hari', remaining_days_label('2028-03-01', $this->utc('2028-02-28 05:00:00')));
        $this->assertSame('365 hari', remaining_days_label('2027-10-10', $this->utc('2026-10-10 05:00:00')));
    }

    public function testInvalidOrMissingDatesNeverReadAsActive(): void
    {
        $now = $this->utc('2026-10-10 05:00:00');
        $this->assertSame('Akun telah kedaluwarsa', remaining_days_label('0000-00-00', $now));
        $this->assertSame('Akun telah kedaluwarsa', remaining_days_label('2026-13-40', $now));
        $this->assertSame('Akun telah kedaluwarsa', remaining_days_label('not a date', $now));
        $this->assertSame('Tanggal kedaluwarsa tidak tersedia', remaining_days_label(null, $now));
        $this->assertSame('Tanggal kedaluwarsa tidak tersedia', remaining_days_label('', $now));
    }

    public function testIndonesianLongDateUsesWib(): void
    {
        $this->assertSame('10 Oktober 2026', format_date_id($this->utc('2026-10-10 05:00:00')));
        // 23:30 UTC on 31 December is already 1 January in WIB.
        $this->assertSame('1 Januari 2027', format_date_id($this->utc('2026-12-31 23:30:00')));
    }

    public function testLegacyExpiryParsing(): void
    {
        $this->assertSame('2026-10-10', legacy_expiry_date('2026-10-10')?->format('Y-m-d'));
        $this->assertSame('2026-10-10', legacy_expiry_date('2026-10-10 00:00:00')?->format('Y-m-d'));
        $this->assertNull(legacy_expiry_date('0000-00-00'));
        $this->assertNull(legacy_expiry_date('2026-02-30'));
        $this->assertNull(legacy_expiry_date(null));
    }

    private function utc(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }
}
