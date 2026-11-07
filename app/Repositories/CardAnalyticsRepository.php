<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

/**
 * Atomic daily counters. Unique keys make concurrent requests safe without locks (TECHNICAL).
 */
class CardAnalyticsRepository
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /**
     * Counts one view; when $visitorHash is new for the day, also counts one unique visitor.
     */
    public function recordView(int $cardUserId, string $dateWib, ?string $visitorHash): void
    {
        $isNewVisitor = false;
        if ($visitorHash !== null) {
            $this->db->query(
                "INSERT IGNORE INTO {$this->db->prefixTable('card_daily_visitors')} (card_user_id, date_wib, visitor_hash) VALUES (?, ?, ?)",
                [$cardUserId, $dateWib, $visitorHash]
            );
            $isNewVisitor = $this->db->affectedRows() === 1;
        }
        $this->db->query(
            "INSERT INTO {$this->db->prefixTable('card_daily_metrics')} (card_user_id, date_wib, views, unique_visitors, contact_clicks)
             VALUES (?, ?, 1, ?, 0)
             ON DUPLICATE KEY UPDATE views = views + 1, unique_visitors = unique_visitors + VALUES(unique_visitors)",
            [$cardUserId, $dateWib, $isNewVisitor ? 1 : 0]
        );
    }

    public function recordContactClick(int $cardUserId, string $dateWib): void
    {
        $this->db->query(
            "INSERT INTO {$this->db->prefixTable('card_daily_metrics')} (card_user_id, date_wib, views, unique_visitors, contact_clicks)
             VALUES (?, ?, 0, 0, 1)
             ON DUPLICATE KEY UPDATE contact_clicks = contact_clicks + 1",
            [$cardUserId, $dateWib]
        );
    }

    /**
     * @return array<string, array{views: int, unique_visitors: int, contact_clicks: int}> keyed by Y-m-d
     */
    public function month(int $cardUserId, string $firstDay, string $nextMonthFirstDay): array
    {
        $rows = $this->db->table('card_daily_metrics')
            ->select('date_wib, views, unique_visitors, contact_clicks')
            ->where('card_user_id', $cardUserId)
            ->where('date_wib >=', $firstDay)
            ->where('date_wib <', $nextMonthFirstDay)
            ->get()->getResultArray();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['date_wib']] = ['views' => (int) $row['views'], 'unique_visitors' => (int) $row['unique_visitors'], 'contact_clicks' => (int) $row['contact_clicks']];
        }

        return $byDay;
    }

    /**
     * Deletes visitor hashes and aggregates older than the retention windows (PRD section 6).
     */
    public function purge(string $visitorsBefore, string $metricsBefore): array
    {
        $this->db->table('card_daily_visitors')->where('date_wib <', $visitorsBefore)->delete();
        $visitors = $this->db->affectedRows();
        $this->db->table('card_daily_metrics')->where('date_wib <', $metricsBefore)->delete();

        return ['visitors' => $visitors, 'metrics' => $this->db->affectedRows()];
    }
}
