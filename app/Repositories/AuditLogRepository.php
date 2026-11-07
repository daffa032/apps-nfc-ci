<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

/**
 * Append-only access to audit_events. There is deliberately no update or delete method (ARC-04).
 */
class AuditLogRepository
{
    public const PAGE_SIZE = 25;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function append(array $event): void
    {
        $this->db->table('audit_events')->insert($event);
    }

    /**
     * @param array{action?: string, outcome?: string, actor?: string, target?: string, from?: string, to?: string} $filters
     *        Values are already validated by the service; dates are UTC 'Y-m-d H:i:s'.
     * @return array{rows: list<array>, total: int}
     */
    public function search(array $filters, int $page): array
    {
        $builder = $this->db->table('audit_events e')
            ->select('e.*, u.username AS actor_username')
            ->join('table_user u', 'u.id_user = e.actor_id', 'left');

        if (($filters['action'] ?? '') !== '') {
            $builder->where('e.action', $filters['action']);
        }
        if (($filters['outcome'] ?? '') !== '') {
            $builder->where('e.outcome', $filters['outcome']);
        }
        if (($filters['actor'] ?? '') !== '') {
            $builder->like('u.username', $filters['actor']);
        }
        if (($filters['target'] ?? '') !== '') {
            $builder->groupStart()->where('e.target_id', $filters['target'])->orWhere('e.request_id', $filters['target'])->groupEnd();
        }
        if (($filters['from'] ?? '') !== '') {
            $builder->where('e.occurred_at >=', $filters['from']);
        }
        if (($filters['to'] ?? '') !== '') {
            $builder->where('e.occurred_at <', $filters['to']);
        }

        $total = $builder->countAllResults(false);
        $rows = $builder->orderBy('e.occurred_at', 'DESC')->orderBy('e.id', 'DESC')
            ->limit(self::PAGE_SIZE, max(0, ($page - 1) * self::PAGE_SIZE))
            ->get()->getResultArray();

        return ['rows' => $rows, 'total' => $total];
    }
}
