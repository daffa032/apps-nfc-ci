<?php

declare(strict_types=1);

use App\Libraries\QrCode;
use PHPUnit\Framework\TestCase;

final class QrCodeTest extends TestCase
{
    /**
     * Fixtures come from libqrencode 4.1.1 (`qrencode -l M -8 -m 0 -t ASCII`). Each matrix must match
     * bit for bit when the same mask is used, which checks data encoding, Reed-Solomon, placement,
     * function patterns, format and version information.
     */
    public function testMatchesLibqrencodeBitForBit(): void
    {
        $cases = json_decode((string) file_get_contents(__DIR__ . '/../_support/Fixtures/qrencode-m.json'), true);
        foreach ($cases as $case) {
            $matched = array_filter(range(0, 7), static fn (int $mask): bool => QrCode::encode($case['text'], $mask)->rows() === $case['rows']);
            self::assertCount(1, $matched, 'No mask reproduces libqrencode for ' . strlen($case['text']) . ' bytes');
        }
    }

    public function testPicksSmallestVersionAndRejectsOverlongText(): void
    {
        self::assertSame(1, QrCode::encode('x')->version);
        self::assertSame(21, QrCode::encode('x')->size());
        self::assertSame(3, QrCode::encode('https://qr-code.id/nfc/dannylim')->version);
        self::assertSame(10, QrCode::encode(str_repeat('a', 213))->version);

        $this->expectException(InvalidArgumentException::class);
        QrCode::encode(str_repeat('a', 214));
    }

    public function testSvgEscapesTheLabelAndHasAQuietZone(): void
    {
        $svg = QrCode::encode('https://example.test/a')->svg('Kode "<b>"');
        self::assertStringContainsString('aria-label="Kode &quot;&lt;b&gt;&quot;"', $svg);
        self::assertStringContainsString('viewBox="0 0 33 33"', $svg, '25 modules + 4 quiet modules on each side');
        self::assertStringNotContainsString('<b>', $svg);
    }
}
