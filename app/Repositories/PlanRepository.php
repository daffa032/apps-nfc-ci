<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;
use Throwable;

class PlanRepository
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function all(): array
    {
        return $this->db->table('plans')->orderBy('id', 'ASC')->get()->getResultArray();
    }

    /**
     * Writes a new price version and updates the plan's current values in one transaction,
     * with the plan row locked so two edits cannot reuse a version number.
     */
    public function saveVersion(string $code, ?int $priceIdr, ?int $durationMonths, bool $enabled, int $actorId): int
    {
        $this->db->transBegin();

        try {
            $plan = $this->db->query("SELECT * FROM {$this->db->prefixTable('plans')} WHERE code = ? FOR UPDATE", [$code])->getRowArray();
            if (! $plan) {
                throw new DomainException(lang('Master.planMissing'));
            }
            $version = (int) $plan['version'] + 1;
            $now     = gmdate('Y-m-d H:i:s');
            $this->db->table('plan_price_versions')->insert([
                'plan_id' => $plan['id'], 'version' => $version, 'price_idr' => $priceIdr, 'duration_months' => $durationMonths,
                'enabled' => $enabled ? 1 : 0, 'actor_id' => $actorId, 'created_at' => $now,
            ]);
            $this->db->table('plans')->where('id', $plan['id'])->update([
                'price_idr' => $priceIdr, 'duration_months' => $durationMonths, 'enabled' => $enabled ? 1 : 0,
                'version'   => $version, 'updated_at' => $now,
            ]);
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Paket gagal disimpan.');
            }
            $this->db->transCommit();

            return $version;
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }
}
