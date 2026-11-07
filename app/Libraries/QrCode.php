<?php

declare(strict_types=1);

namespace App\Libraries;

use InvalidArgumentException;

/**
 * Minimal QR Code encoder (ISO/IEC 18004) for the share page (PRD QR-02/03): byte mode, error
 * correction level M, versions 1–10 (up to 213 bytes, plenty for a card URL). Pure: no I/O.
 * The matrix is verified against libqrencode in tests/unit/QrCodeTest.php.
 */
final class QrCode
{
    /**
     * Per version (index 1–10), level M: [EC codewords per block, [[blocks, data codewords], ...]].
     */
    private const BLOCKS = [
        1  => [10, [[1, 16]]],
        2  => [16, [[1, 28]]],
        3  => [26, [[1, 44]]],
        4  => [18, [[2, 32]]],
        5  => [24, [[2, 43]]],
        6  => [16, [[4, 27]]],
        7  => [18, [[4, 31]]],
        8  => [22, [[2, 38], [2, 39]]],
        9  => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];

    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /**
     * @var list<list<bool>> [row][column], true = dark
     */
    private array $modules = [];

    /**
     * @var list<list<bool>> function patterns that data and masks must not touch
     */
    private array $reserved = [];

    private int $size;

    private function __construct(public readonly int $version, public readonly int $mask)
    {
        $this->size = $version * 4 + 17;
    }

    /**
     * Encodes $text. $forceMask (0–7) is only for tests; normally the lowest-penalty mask is used.
     */
    public static function encode(string $text, ?int $forceMask = null): self
    {
        $data    = array_values(unpack('C*', $text) ?: []);
        $version = null;

        foreach (self::BLOCKS as $v => [, $groups]) {
            $capacity  = array_sum(array_map(static fn (array $g): int => $g[0] * $g[1], $groups));
            $countBits = $v < 10 ? 8 : 16;
            if (4 + $countBits + 8 * count($data) <= $capacity * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new InvalidArgumentException('Text is too long for a QR code.');
        }

        $codewords = self::codewords($data, $version);
        $best      = null;

        foreach ($forceMask === null ? range(0, 7) : [$forceMask] as $mask) {
            $qr = new self($version, $mask);
            $qr->drawFunctionPatterns();
            $qr->drawData($codewords);
            $qr->applyMask();
            $qr->drawFormat();
            $penalty = $forceMask === null ? $qr->penalty() : 0;
            if ($best === null || $penalty < $best[0]) {
                $best = [$penalty, $qr];
            }
        }

        return $best[1];
    }

    public function size(): int
    {
        return $this->size;
    }

    public function isDark(int $row, int $column): bool
    {
        return $this->modules[$row][$column];
    }

    /**
     * Rows of "1"/"0", for the browser to draw the downloadable PNG.
     *
     * @return list<string>
     */
    public function rows(): array
    {
        return array_map(static fn (array $row): string => implode('', array_map('intval', $row)), $this->modules);
    }

    /**
     * Crisp, scalable SVG with a 4-module quiet zone. $label becomes the accessible name.
     */
    public function svg(string $label): string
    {
        $path = '';

        foreach ($this->modules as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + 4) . ' ' . ($y + 4) . 'h1v1h-1z';
                }
            }
        }
        $box = $this->size + 8;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $box . ' ' . $box . '" role="img" aria-label="'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" shape-rendering="crispEdges">'
            . '<rect width="' . $box . '" height="' . $box . '" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
    }

    /**
     * @param list<int> $data
     *
     * @return list<int>
     */
    private static function codewords(array $data, int $version): array
    {
        [$ecLength, $groups] = self::BLOCKS[$version];
        $capacity            = array_sum(array_map(static fn (array $g): int => $g[0] * $g[1], $groups));

        $bits = '0100' . str_pad(decbin(count($data)), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);

        foreach ($data as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $bytes = array_map('bindec', str_split($bits, 8));

        for ($pad = 0xEC; count($bytes) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $bytes[] = $pad;
        }

        $blocks = [];
        $offset = 0;

        foreach ($groups as [$count, $length]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($bytes, $offset, $length);
                $offset += $length;
                $blocks[] = [$block, self::reedSolomon($block, $ecLength)];
            }
        }

        $result  = [];
        $longest = max(array_map(static fn (array $b): int => count($b[0]), $blocks));

        for ($i = 0; $i < $longest; $i++) {
            foreach ($blocks as [$block]) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecLength; $i++) {
            foreach ($blocks as [, $ec]) {
                $result[] = $ec[$i];
            }
        }

        return $result;
    }

    /**
     * @param list<int> $data
     *
     * @return list<int>
     */
    private static function reedSolomon(array $data, int $degree): array
    {
        // Generator polynomial (x - a^0)(x - a^1)...(x - a^(degree-1)), leading 1 dropped.
        $generator              = array_fill(0, $degree, 0);
        $generator[$degree - 1] = 1;
        $root                   = 1;

        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $generator[$j] = self::gfMultiply($generator[$j], $root) ^ ($generator[$j + 1] ?? 0);
            }
            $root = self::gfMultiply($root, 0x02);
        }

        $remainder = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor      = $byte ^ array_shift($remainder);
            $remainder[] = 0;

            foreach ($generator as $j => $coefficient) {
                $remainder[$j] ^= self::gfMultiply($coefficient, $factor);
            }
        }

        return $remainder;
    }

    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;

        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z;
    }

    private function set(int $x, int $y, bool $dark, bool $reserve = true): void
    {
        $this->modules[$y][$x] = $dark;
        if ($reserve) {
            $this->reserved[$y][$x] = true;
        }
    }

    private function drawFunctionPatterns(): void
    {
        $this->modules  = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->reserved = array_fill(0, $this->size, array_fill(0, $this->size, false));

        for ($i = 0; $i < $this->size; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }

        foreach ([[3, 3], [$this->size - 4, 3], [3, $this->size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                        $distance = max(abs($dx), abs($dy));
                        $this->set($x, $y, $distance !== 2 && $distance !== 4);
                    }
                }
            }
        }

        $positions = self::ALIGNMENT[$this->version];
        $last      = count($positions) - 1;

        foreach ($positions as $i => $cy) {
            foreach ($positions as $j => $cx) {
                // Skip the three corners that already hold finder patterns.
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }

                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }

        // Format areas are reserved now and filled once the mask is known.
        $this->drawFormatBits(0);

        if ($this->version >= 7) {
            $remainder = $this->version;

            for ($i = 0; $i < 12; $i++) {
                $remainder = ($remainder << 1) ^ (($remainder >> 11) * 0x1F25);
            }
            $bits = ($this->version << 12) | $remainder;

            for ($i = 0; $i < 18; $i++) {
                $dark = (($bits >> $i) & 1) === 1;
                $a    = $this->size - 11 + $i % 3;
                $b    = intdiv($i, 3);
                $this->set($a, $b, $dark);
                $this->set($b, $a, $dark);
            }
        }
    }

    private function drawFormat(): void
    {
        $this->drawFormatBits($this->mask);
    }

    private function drawFormatBits(int $mask): void
    {
        // Level M has format bits 00.
        $data      = $mask;
        $remainder = $data;

        for ($i = 0; $i < 10; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
        }
        $bits = (($data << 10) | $remainder) ^ 0x5412;
        $bit  = static fn (int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));

        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }

        for ($i = 0; $i < 8; $i++) {
            $this->set($this->size - 1 - $i, 8, $bit($i));
        }

        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $this->size - 15 + $i, $bit($i));
        }
        $this->set(8, $this->size - 8, true);
    }

    /**
     * @param list<int> $codewords
     */
    private function drawData(array $codewords): void
    {
        $i     = 0;
        $total = count($codewords) * 8;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }

            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x      = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y      = $upward ? $this->size - 1 - $vert : $vert;
                    if (! $this->reserved[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->reserved[$y][$x]) {
                    continue;
                }
                $flip = match ($this->mask) {
                    0       => ($x + $y) % 2 === 0,
                    1       => $y % 2 === 0,
                    2       => $x % 3 === 0,
                    3       => ($x + $y) % 3 === 0,
                    4       => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5       => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6       => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
                };
                if ($flip) {
                    $this->modules[$y][$x] = ! $this->modules[$y][$x];
                }
            }
        }
    }

    /**
     * Standard penalty (runs, 2×2 blocks, finder-like patterns, dark balance); any mask decodes,
     * the lowest penalty just scans more reliably.
     */
    private function penalty(): int
    {
        $score = 0;
        $lines = [];

        for ($i = 0; $i < $this->size; $i++) {
            $lines[] = $this->modules[$i];
            $lines[] = array_column($this->modules, $i);
        }

        foreach ($lines as $line) {
            $run = 1;

            for ($k = 1; $k <= $this->size; $k++) {
                if ($k < $this->size && $line[$k] === $line[$k - 1]) {
                    $run++;

                    continue;
                }
                if ($run >= 5) {
                    $score += $run - 2;
                }
                $run = 1;
            }
            $bits = implode('', array_map('intval', $line));
            $score += 40 * (substr_count($bits, '10111010000') + substr_count($bits, '00001011101'));
        }
        $dark = 0;

        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $dark += $this->modules[$y][$x] ? 1 : 0;
                if ($y + 1 < $this->size && $x + 1 < $this->size
                    && $this->modules[$y][$x] === $this->modules[$y][$x + 1]
                    && $this->modules[$y][$x] === $this->modules[$y + 1][$x]
                    && $this->modules[$y][$x] === $this->modules[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $score += 10 * intdiv(abs($dark * 20 - $this->size * $this->size * 10), $this->size * $this->size);

        return $score;
    }
}
