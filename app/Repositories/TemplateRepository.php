<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TemplateModel;
use App\Services\Cards\TemplateCatalog;
use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;
use Throwable;

class TemplateRepository
{
    public function __construct(private ?TemplateModel $templates = null, private ?BaseConnection $db = null)
    {
        $this->templates ??= new TemplateModel();
        $this->db ??= db_connect();
    }

    public function all(): array
    {
        return $this->templates->orderBy('display_order', 'ASC')->orderBy('id_template', 'ASC')->findAll();
    }

    /**
     * Active slot limits set in Master Templates, or the catalog defaults when the row is missing.
     *
     * @return array{basic: int, total: int}
     */
    public function slotLimits(): array
    {
        $row = $this->db->table('template_slot_limits')->where('id', 1)->get()->getRowArray();

        return $row === null
            ? ['basic' => TemplateCatalog::DEFAULT_MAX_ACTIVE_BASIC, 'total' => TemplateCatalog::DEFAULT_MAX_ACTIVE_TOTAL]
            : ['basic' => (int) $row['max_active_basic'], 'total' => (int) $row['max_active_total']];
    }

    /**
     * Saves new slot limits inside the catalog lock, so no activation can slip in between the check
     * and the write.
     *
     * @param callable(list<array>): void $check receives every template row (locked)
     */
    public function saveSlotLimits(int $basic, int $total, int $actorId, callable $check): void
    {
        $this->db->transBegin();

        try {
            $rows = $this->db->query(
                "SELECT * FROM {$this->db->prefixTable('templates')} ORDER BY display_order, id_template FOR UPDATE",
            )->getResultArray();
            $check($rows);

            $this->db->query(
                'INSERT INTO ' . $this->db->prefixTable('template_slot_limits') . ' (id, max_active_basic, max_active_total, updated_by, updated_at) VALUES (1, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE max_active_basic = VALUES(max_active_basic), max_active_total = VALUES(max_active_total), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
                [$basic, $total, $actorId > 0 ? $actorId : null, gmdate('Y-m-d H:i:s')],
            );
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Batas slot gagal disimpan.');
            }
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    public function find(int $templateId): ?array
    {
        return $this->templates->find($templateId);
    }

    /**
     * Normalised HTML of an uploaded template's current version, or null.
     */
    public function currentHtml(int $templateId): ?string
    {
        $row = $this->db->table('templates t')
            ->select('v.html')
            ->join('template_versions v', 'v.id = t.current_version_id')
            ->where('t.id_template', $templateId)
            ->get()->getRowArray();

        return $row === null ? null : (string) $row['html'];
    }

    /**
     * Adds an uploaded template, inactive or active, with its first immutable version, inside the
     * catalog lock so the slot check sees every row (PRD MA-02).
     *
     * @param callable(list<array>): void $check receives every row (locked) including the new one
     */
    public function createUploaded(string $name, string $planCode, bool $active, string $description, string $html, int $actorId, callable $check): int
    {
        $this->db->transBegin();

        try {
            $rows = $this->db->query(
                "SELECT * FROM {$this->db->prefixTable('templates')} ORDER BY display_order, id_template FOR UPDATE",
            )->getResultArray();
            $order = $rows === [] ? 1 : max(array_map(static fn (array $row): int => (int) $row['display_order'], $rows)) + 1;
            // The candidate needs a catalog-valid file name, or the slot count would skip it.
            $check([...$rows, ['id_template' => 0, 'nama_file' => TemplateCatalog::CUSTOM_PREFIX . '9999999999', 'plan_code' => $planCode, 'is_active' => $active ? 1 : 0]]);

            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('templates')->insert([
                'nama_template' => $name, 'nama_file' => 'pending', 'images_template' => '',
                'plan_code'     => $planCode, 'is_active' => $active ? 1 : 0, 'display_order' => min(999, $order),
                'description'   => $description === '' ? null : $description,
            ]);
            $templateId = (int) $this->db->insertID();
            $this->db->table('template_versions')->insert([
                'template_id' => $templateId, 'version' => 1, 'html' => $html,
                'checksum'    => hash('sha256', $html), 'created_by' => $actorId > 0 ? $actorId : null, 'created_at' => $now,
            ]);
            $versionId = (int) $this->db->insertID();
            $this->db->table('templates')->where('id_template', $templateId)->update([
                'nama_file' => TemplateCatalog::CUSTOM_PREFIX . $templateId, 'current_version_id' => $versionId,
            ]);
            if ($templateId < 1 || $versionId < 1 || $this->db->transStatus() === false) {
                throw new RuntimeException('Template gagal disimpan.');
            }
            $this->db->transCommit();

            return $templateId;
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    /**
     * Renames a template inside the catalog lock, so two admins cannot give two templates the same
     * name at once. Returns the previous name.
     *
     * @param callable(list<array>): void $check receives every row (locked), before the change
     */
    public function renameLocked(int $templateId, string $name, callable $check): string
    {
        $this->db->transBegin();

        try {
            $rows = $this->db->query(
                "SELECT * FROM {$this->db->prefixTable('templates')} ORDER BY display_order, id_template FOR UPDATE",
            )->getResultArray();
            $current = null;

            foreach ($rows as $row) {
                if ((int) $row['id_template'] === $templateId) {
                    $current = $row;
                }
            }
            if ($current === null) {
                throw new DomainException(lang('Master.templateMissing'));
            }
            $check($rows);

            if (! $this->templates->update($templateId, ['nama_template' => $name]) || $this->db->transStatus() === false) {
                throw new RuntimeException('Nama template gagal diperbarui.');
            }
            $this->db->transCommit();

            return (string) $current['nama_template'];
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    /**
     * Changes a template's status and plan inside a lock on the whole catalog, so two admins cannot
     * both pass the slot check (PRD MA-02: limits from slotLimits()).
     *
     * @param callable(list<array>): void $check receives every row (locked) with the change applied
     */
    public function updateLocked(int $templateId, array $changes, callable $check): void
    {
        $this->db->transBegin();

        try {
            $rows = $this->db->query(
                "SELECT * FROM {$this->db->prefixTable('templates')} ORDER BY display_order, id_template FOR UPDATE",
            )->getResultArray();
            $found = false;

            foreach ($rows as &$row) {
                if ((int) $row['id_template'] === $templateId) {
                    $row   = $changes + $row;
                    $found = true;
                }
            }
            unset($row);
            if (! $found) {
                throw new DomainException(lang('Master.templateMissing'));
            }
            $check($rows);

            if (! $this->templates->update($templateId, $changes) || $this->db->transStatus() === false) {
                throw new RuntimeException('Template gagal diperbarui.');
            }
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }
}
