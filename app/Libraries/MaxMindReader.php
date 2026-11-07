<?php

declare(strict_types=1);

namespace App\Libraries;

use InvalidArgumentException;
use RuntimeException;

/**
 * Minimal read-only reader for MaxMind DB files (format 2.0, e.g. GeoLite2-Country.mmdb), used to
 * guess a visitor's country for the first interface language (PRD LANG-03). No dependency: the
 * project has no Composer vendor folder. Supports 24/28/32-bit record trees, IPv4 and IPv6 lookups
 * and the data types a country database uses. Pure lookup; the IP is never stored.
 *
 * Spec: https://maxmind.github.io/MaxMind-DB/
 */
final class MaxMindReader
{
    private const METADATA_MARKER = "\xAB\xCD\xEFMaxMind.com";
    private const DATA_SECTION_SEPARATOR = 16;

    /** @var resource */
    private $file;
    private int $nodeCount;
    private int $recordSize;
    private int $ipVersion;
    private int $searchTreeSize;
    private int $dataStart;
    private ?int $ipv4Start = null;

    public function __construct(string $path)
    {
        $file = is_file($path) && is_readable($path) ? fopen($path, 'rb') : false;
        if ($file === false) {
            throw new InvalidArgumentException('MaxMind database not readable.');
        }
        $this->file = $file;

        $size = (int) fstat($file)['size'];
        $tailLength = min($size, 128 * 1024);
        $tail = $this->read($size - $tailLength, $tailLength);
        $markerAt = strrpos($tail, self::METADATA_MARKER);
        if ($markerAt === false) {
            throw new RuntimeException('Not a MaxMind DB file.');
        }
        $metadataStart = $size - $tailLength + $markerAt + strlen(self::METADATA_MARKER);
        [$metadata] = $this->decode($metadataStart, $metadataStart);
        if (!is_array($metadata) || ($metadata['binary_format_major_version'] ?? null) !== 2) {
            throw new RuntimeException('Unsupported MaxMind DB format.');
        }

        $this->nodeCount = (int) $metadata['node_count'];
        $this->recordSize = (int) $metadata['record_size'];
        $this->ipVersion = (int) $metadata['ip_version'];
        if (!in_array($this->recordSize, [24, 28, 32], true)) {
            throw new RuntimeException('Unsupported record size.');
        }
        $this->searchTreeSize = intdiv($this->recordSize * 2, 8) * $this->nodeCount;
        $this->dataStart = $this->searchTreeSize + self::DATA_SECTION_SEPARATOR;
    }

    public function __destruct()
    {
        if (is_resource($this->file)) {
            fclose($this->file);
        }
    }

    /**
     * The data record for $ip, or null when the address is not in the database.
     */
    public function get(string $ip): mixed
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            throw new InvalidArgumentException('Invalid IP address.');
        }
        $bits = strlen($packed) * 8;
        if ($bits === 128 && $this->ipVersion === 4) {
            return null;
        }

        $node = $bits === 32 && $this->ipVersion === 6 ? $this->ipv4StartNode() : 0;
        for ($i = 0; $i < $bits && $node < $this->nodeCount; $i++) {
            $bit = (ord($packed[$i >> 3]) >> (7 - ($i & 7))) & 1;
            $node = $this->readRecord($node, $bit);
        }
        if ($node === $this->nodeCount) {
            return null;
        }
        if ($node < $this->nodeCount) {
            throw new RuntimeException('Invalid search tree.');
        }
        $offset = $node - $this->nodeCount + $this->searchTreeSize;

        return $this->decode($offset, $this->searchTreeSize + self::DATA_SECTION_SEPARATOR)[0];
    }

    /**
     * ISO 3166-1 alpha-2 country of $ip (e.g. "ID"), or null when unknown.
     */
    public function countryCode(string $ip): ?string
    {
        $record = $this->get($ip);
        $code = is_array($record) ? ($record['country']['iso_code'] ?? $record['registered_country']['iso_code'] ?? null) : null;

        return is_string($code) && preg_match('/\A[A-Z]{2}\z/', $code) === 1 ? $code : null;
    }

    private function ipv4StartNode(): int
    {
        if ($this->ipv4Start === null) {
            // IPv4 addresses live under ::/96 in an IPv6 tree.
            $node = 0;
            for ($i = 0; $i < 96 && $node < $this->nodeCount; $i++) {
                $node = $this->readRecord($node, 0);
            }
            $this->ipv4Start = $node;
        }

        return $this->ipv4Start;
    }

    private function readRecord(int $node, int $index): int
    {
        $bytes = intdiv($this->recordSize * 2, 8);
        $raw = $this->read($node * $bytes, $bytes);

        return match ($this->recordSize) {
            24 => self::uint(substr($raw, $index * 3, 3)),
            28 => $index === 0
                ? ((ord($raw[3]) & 0xF0) << 20) | self::uint(substr($raw, 0, 3))
                : ((ord($raw[3]) & 0x0F) << 24) | self::uint(substr($raw, 4, 3)),
            default => self::uint(substr($raw, $index * 4, 4)),
        };
    }

    /**
     * @return array{0: mixed, 1: int} value and the offset after it
     */
    private function decode(int $offset, int $pointerBase): array
    {
        $control = ord($this->read($offset++, 1));
        $type = $control >> 5;

        if ($type === 1) {
            $size = ($control >> 3) & 0x3;
            $bytes = $this->read($offset, $size + 1);
            $value = $control & 0x7;
            $pointer = match ($size) {
                0 => ($value << 8) | ord($bytes[0]),
                1 => (($value << 16) | self::uint(substr($bytes, 0, 2))) + 2048,
                2 => (($value << 24) | self::uint(substr($bytes, 0, 3))) + 526336,
                default => self::uint(substr($bytes, 0, 4)),
            };
            [$target] = $this->decode($pointerBase + $pointer, $pointerBase);

            return [$target, $offset + $size + 1];
        }
        if ($type === 0) {
            $type = 7 + ord($this->read($offset++, 1));
        }

        $size = $control & 0x1F;
        if ($size >= 29) {
            $extra = $size - 28;
            $value = self::uint($this->read($offset, $extra));
            $offset += $extra;
            $size = match ($extra) {
                1 => 29 + $value,
                2 => 285 + $value,
                default => 65821 + $value,
            };
        }

        switch ($type) {
            case 2: // UTF-8 string
            case 4: // bytes
                return [$size === 0 ? '' : $this->read($offset, $size), $offset + $size];
            case 3: // double
                return [unpack('E', $this->read($offset, 8))[1], $offset + 8];
            case 15: // float
                return [unpack('G', $this->read($offset, 4))[1], $offset + 4];
            case 5: // uint16
            case 6: // uint32
            case 9: // uint64
            case 10: // uint128 (only small values fit; country DBs do not use them)
            case 8: // int32
                $value = $size === 0 ? 0 : self::uint($this->read($offset, $size));
                if ($type === 8 && $size === 4 && $value >= 0x80000000) {
                    $value -= 0x100000000;
                }

                return [$value, $offset + $size];
            case 14: // boolean
                return [$size !== 0, $offset];
            case 7: // map
                $map = [];
                for ($i = 0; $i < $size; $i++) {
                    [$key, $offset] = $this->decode($offset, $pointerBase);
                    [$map[(string) $key], $offset] = $this->decode($offset, $pointerBase);
                }

                return [$map, $offset];
            case 11: // array
                $list = [];
                for ($i = 0; $i < $size; $i++) {
                    [$list[], $offset] = $this->decode($offset, $pointerBase);
                }

                return [$list, $offset];
            default:
                throw new RuntimeException('Unsupported MaxMind DB data type ' . $type . '.');
        }
    }

    private function read(int $offset, int $length): string
    {
        if ($length === 0) {
            return '';
        }
        fseek($this->file, $offset);
        $data = fread($this->file, $length);
        if ($data === false || strlen($data) !== $length) {
            throw new RuntimeException('Unexpected end of MaxMind DB.');
        }

        return $data;
    }

    private static function uint(string $bytes): int
    {
        $value = 0;
        foreach (str_split($bytes) as $byte) {
            $value = ($value << 8) | ord($byte);
        }

        return $value;
    }
}
