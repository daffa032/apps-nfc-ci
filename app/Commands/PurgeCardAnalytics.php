<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Analytics\CardAnalyticsService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;

/**
 * Daily cron: php spark analytics:purge. Deletes visitor hashes older than 30 days and
 * aggregates older than 12 months (Config\Analytics).
 */
class PurgeCardAnalytics extends BaseCommand
{
    protected $group = 'Analytics';
    protected $name = 'analytics:purge';
    protected $description = 'Hapus hash pengunjung dan agregat statistik yang melewati masa simpan.';

    public function run(array $params)
    {
        $deleted = (new CardAnalyticsService())->purge(new DateTimeImmutable('now'));
        CLI::write('Hash pengunjung dihapus: ' . $deleted['visitors'] . '; agregat harian dihapus: ' . $deleted['metrics']);
    }
}
