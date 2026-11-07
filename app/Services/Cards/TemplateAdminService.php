<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Exceptions\FieldException;
use App\Repositories\TemplateRepository;
use DomainException;

/**
 * Super Admin catalog management (PRD MA-02). Templates are never hard-deleted: deactivating one
 * hides it from new selections while cards that already use it keep rendering.
 */
final class TemplateAdminService
{
    public function __construct(private ?TemplateRepository $templates = null)
    {
        $this->templates ??= new TemplateRepository();
    }

    /**
     * @return array{templates: list<array>, activeBasic: int, activeTotal: int, limits: array{basic: int, total: int}}
     */
    public function overview(): array
    {
        $rows = TemplateCatalog::availableRows($this->templates->all());

        return ['templates' => $rows, 'limits' => $this->templates->slotLimits()] + self::counts($rows);
    }

    /**
     * New active-slot limits (PRD MA-02). Basic counts towards the total, so Basic may not exceed
     * it; a limit below what is active now is refused, never applied by switching templates off.
     */
    public function updateLimits(string $basic, string $total, int $actorId): void
    {
        foreach (['max_active_basic' => $basic, 'max_active_total' => $total] as $field => $value) {
            if (preg_match('/\A[0-9]{1,2}\z/', trim($value)) !== 1 || (int) $value < 1 || (int) $value > TemplateCatalog::MAX_SLOT_LIMIT) {
                throw new FieldException($field, lang('Master.errLimit', [TemplateCatalog::MAX_SLOT_LIMIT]));
            }
        }
        $basic = (int) $basic;
        $total = (int) $total;
        if ($basic > $total) {
            throw new FieldException('max_active_basic', lang('Master.errLimitBasicOverTotal'));
        }

        $this->templates->saveSlotLimits($basic, $total, $actorId, static function (array $rows) use ($basic, $total): void {
            $counts = self::counts(TemplateCatalog::availableRows($rows));
            if ($counts['activeBasic'] > $basic) {
                throw new FieldException('max_active_basic', lang('Master.errLimitBelowActive', [$counts['activeBasic']]));
            }
            if ($counts['activeTotal'] > $total) {
                throw new FieldException('max_active_total', lang('Master.errLimitBelowActive', [$counts['activeTotal']]));
            }
        });
    }

    /**
     * Adds an uploaded HTML template (PRD MA-02, MA-03). Default inactive; activation obeys the
     * same slot rules as status changes. Returns the new template ID.
     */
    public function create(string $name, string $planCode, bool $active, string $description, string $source, int $actorId): int
    {
        $name = trim($name);
        $description = trim($description);
        if ($name === '' || mb_strlen($name) > 32) {
            throw new FieldException('nama_template', lang('Master.errName'));
        }
        if (!in_array($planCode, ['basic', 'pro'], true)) {
            throw new FieldException('plan_code', lang('Master.errPlan'));
        }
        if (mb_strlen($description) > 160) {
            throw new FieldException('description', lang('Master.errDescription'));
        }
        $html = (new TemplateHtmlSanitizer())->normalize($source);

        return $this->templates->createUploaded($name, $planCode, $active, $description, $html, $actorId, function (array $rows) use ($name): void {
            foreach ($rows as $row) {
                if ((int) $row['id_template'] !== 0 && mb_strtolower(trim((string) $row['nama_template'])) === mb_strtolower($name)) {
                    throw new FieldException('nama_template', lang('Master.errNameTaken', [$name]));
                }
            }
            $this->slotCheck($rows);
        });
    }

    /**
     * Renames a template (built-in or uploaded). The name is only a label: nama_file, the layout,
     * and every card that uses the template stay as they are. Names must be unique, ignoring case,
     * so the catalog never shows two templates with the same name. Returns the previous name.
     */
    public function rename(int $templateId, string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if ($name === '' || mb_strlen($name) > 32) {
            throw new FieldException('nama_template', lang('Master.errName'));
        }

        return $this->templates->renameLocked($templateId, $name, static function (array $rows) use ($templateId, $name): void {
            foreach ($rows as $row) {
                if ((int) $row['id_template'] !== $templateId && mb_strtolower(trim((string) $row['nama_template'])) === mb_strtolower($name)) {
                    throw new FieldException('nama_template', lang('Master.errNameTaken', [$name]));
                }
            }
        });
    }

    public function update(int $templateId, string $planCode, bool $active, int $order): void
    {
        if (!in_array($planCode, ['basic', 'pro'], true)) {
            throw new DomainException(lang('Master.errPlan'));
        }
        if ($order < 0 || $order > 999) {
            throw new DomainException(lang('Master.errOrder'));
        }

        $this->templates->updateLocked(
            $templateId,
            ['plan_code' => $planCode, 'is_active' => $active ? 1 : 0, 'display_order' => $order],
            $this->slotCheck(...)
        );
    }

    /**
     * Slot rules on the locked catalog with the change applied: the limits set in Master Templates
     * and at least one active Basic.
     */
    private function slotCheck(array $rows): void
    {
        $limits = $this->templates->slotLimits();
        $counts = self::counts(TemplateCatalog::availableRows($rows));
        if ($counts['activeBasic'] > $limits['basic']) {
            throw new DomainException(lang('Master.basicFull', [$limits['basic']]));
        }
        if ($counts['activeTotal'] > $limits['total']) {
            throw new DomainException(lang('Master.allFull', [$limits['total']]));
        }
        if ($counts['activeBasic'] < 1) {
            throw new DomainException(lang('Master.needBasic'));
        }
    }

    /**
     * @return array{activeBasic: int, activeTotal: int}
     */
    private static function counts(array $rows): array
    {
        $active = array_filter($rows, static fn (array $row): bool => TemplateSelectionService::isActive($row));

        return [
            'activeBasic' => count(array_filter($active, static fn (array $row): bool => ($row['plan_code'] ?? 'basic') === 'basic')),
            'activeTotal' => count($active),
        ];
    }
}
