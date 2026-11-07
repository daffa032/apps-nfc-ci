<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\MaxMindReader;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use FilesystemIterator;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Weekly cron: php spark geoip:update. Downloads GeoLite2 Country with the MaxMind account in .env,
 * checks the SHA-256 published next to it, opens the new file once as a test lookup, and only then
 * replaces the current database atomically. On any failure the old file stays in place.
 */
class UpdateGeoIp extends BaseCommand
{
    private const URL = 'https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=';

    protected $group       = 'Locale';
    protected $name        = 'geoip:update';
    protected $description = 'Unduh dan pasang GeoLite2 Country terbaru untuk deteksi bahasa awal.';

    public function run(array $params)
    {
        $config = config('Locale');
        if ($config->geoipAccountId === '' || $config->geoipLicenseKey === '' || $config->geoipDatabase === '') {
            CLI::error('Isi locale.geoipAccountId dan locale.geoipLicenseKey di .env terlebih dahulu.');

            return EXIT_ERROR;
        }

        $target = $config->geoipDatabase;
        $dir    = dirname($target);
        $work   = $dir . '/.update-' . bin2hex(random_bytes(6));

        try {
            if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
                throw new RuntimeException('Folder GeoIP tidak dapat dibuat.');
            }
            mkdir($work, 0700);
            $archive = $work . '/GeoLite2-Country.tar.gz';
            $this->download(self::URL . 'tar.gz', $archive, $config->geoipAccountId, $config->geoipLicenseKey);
            $checksum = $this->download(self::URL . 'tar.gz.sha256', null, $config->geoipAccountId, $config->geoipLicenseKey);
            $expected = strtolower(strtok(trim($checksum), " \t") ?: '');
            if (preg_match('/\A[a-f0-9]{64}\z/', $expected) !== 1 || ! hash_equals($expected, hash_file('sha256', $archive))) {
                throw new RuntimeException('Checksum SHA-256 tidak cocok.');
            }

            // Only the .mmdb is taken from the archive, by its own name; no other path is written.
            $mmdb = null;

            foreach (new RecursiveIteratorIterator(new PharData($archive)) as $entry) {
                if ($entry->getFilename() === 'GeoLite2-Country.mmdb') {
                    $mmdb = $work . '/new.mmdb';
                    if (file_put_contents($mmdb, (string) file_get_contents($entry->getPathname())) === false) {
                        throw new RuntimeException('Berkas database tidak dapat disalin.');
                    }
                    break;
                }
            }
            if ($mmdb === null) {
                throw new RuntimeException('GeoLite2-Country.mmdb tidak ada di arsip.');
            }

            // Smoke test before switching: a known Indonesian range must resolve.
            $country = (new MaxMindReader($mmdb))->countryCode('36.64.0.1');
            if ($country === null) {
                throw new RuntimeException('Database baru tidak dapat dibaca.');
            }
            chmod($mmdb, 0640);
            if (! rename($mmdb, $target)) {
                throw new RuntimeException('Database tidak dapat dipasang.');
            }
            CLI::write('GeoLite2 Country terpasang (' . number_format((int) filesize($target)) . ' byte).', 'green');
        } catch (Throwable $exception) {
            CLI::error('Pembaruan GeoIP gagal: ' . $exception->getMessage() . ' Database lama tetap dipakai.');

            return EXIT_ERROR;
        } finally {
            $this->remove($work);
        }

        return EXIT_SUCCESS;
    }

    /**
     * Downloads $url with HTTP basic auth into $file, or returns the body when $file is null.
     */
    private function download(string $url, ?string $file, string $account, string $key): string
    {
        $curl   = curl_init($url);
        $handle = $file === null ? null : fopen($file, 'wb');
        curl_setopt_array($curl, [
            CURLOPT_USERPWD         => $account . ':' . $key,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 5,
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT  => 20,
            CURLOPT_TIMEOUT         => 300,
            CURLOPT_FAILONERROR     => true,
        ] + ($handle === null ? [CURLOPT_RETURNTRANSFER => true] : [CURLOPT_FILE => $handle]));
        $body   = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($curl);
        curl_close($curl);
        if ($handle !== null) {
            fclose($handle);
        }
        if ($body === false || $status !== 200) {
            // The message never contains the licence key.
            throw new RuntimeException('Unduhan gagal (HTTP ' . $status . ($error !== '' ? ', ' . $error : '') . ').');
        }

        return is_string($body) ? $body : '';
    }

    private function remove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
