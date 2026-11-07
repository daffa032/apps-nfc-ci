<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\FieldException;
use App\Services\Audit\AuditLogService;
use App\Services\Cards\PlanAdminService;
use App\Services\Cards\TemplateAdminService;
use App\Services\Cards\TemplateHtmlSanitizer;
use App\Services\Cards\TemplateSamples;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DomainException;
use Throwable;

/**
 * Master Data for Super Admin: plan prices (MA-01) and template catalog (MA-02).
 * The route group restricts access to Super Admin on every request.
 */
class MasterDataController extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    private const PLANS_PAGE = 'dashboard/master/plans';
    private const TEMPLATES_PAGE = 'dashboard/master/templates';

    protected $helpers = ['asset', 'feedback'];

    public function plans(): string
    {
        return view('dashboard/pages/master/plans', $this->dashboardShellData() + [
            'plans' => (new PlanAdminService())->all(),
            'durations' => PlanAdminService::DURATIONS,
            // Which plan form the field errors belong to (both forms share field names).
            'fieldErrorsPlan' => session()->getFlashdata('field_errors_plan'),
            'feedback' => $this->pendingFeedback(),
            'fieldErrors' => $this->pendingFieldErrors(),
        ]);
    }

    public function updatePlan(string $code): RedirectResponse
    {
        try {
            $version = (new AuditLogService())->withRequiredAudit(
                $this->currentUserId(),
                fn (): int => (new PlanAdminService())->update(
                    $code,
                    trim((string) $this->request->getPost('price_idr')),
                    (string) $this->request->getPost('duration_months'),
                    $this->request->getPost('enabled') === '1',
                    $this->currentUserId()
                ),
                static fn (int $version): array => ['plan.updated', 'plan', $code, ['price_idr', 'duration_months', 'enabled'], 'v' . $version]
            );
        } catch (DomainException $exception) {
            return $exception instanceof FieldException
                ? $this->redirectFieldFailure(self::PLANS_PAGE, $exception, ['field_errors_plan' => $code])
                : $this->redirectFailure(self::PLANS_PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Plan update failed: {exception}', ['exception' => $exception]);
            $this->audit('plan.updated', 'failed', 'plan', $code, [], 'server_error');
            return $this->redirectFailure(self::PLANS_PAGE, $this->withReference(lang('Master.planSaveFailed')));
        }

        return $this->redirectSuccess(self::PLANS_PAGE, lang('Master.planSaved', [$version]));
    }

    public function templates(): string
    {
        return view('dashboard/pages/master/templates', $this->dashboardShellData() + (new TemplateAdminService())->overview() + [
            'allowedTags' => TemplateHtmlSanitizer::TAGS,
            'allowedClasses' => [...TemplateHtmlSanitizer::CLASSES, 'tpl-pro-*'],
            'placeholders' => TemplateHtmlSanitizer::PLACEHOLDERS,
            'optionalPlaceholders' => TemplateHtmlSanitizer::OPTIONAL_PLACEHOLDERS,
            'sampleTemplate' => (string) file_get_contents(APPPATH . 'Views/digital_card/sample-template.html'),
            'feedback' => $this->pendingFeedback(),
            'fieldErrors' => $this->pendingFieldErrors(),
            // Row whose rename dialog reopens with the error after a failed rename.
            'renameTemplateId' => (int) session()->getFlashdata('rename_template'),
        ]);
    }

    /**
     * Adds an uploaded HTML template (PRD MA-02, MA-03). The file is read as text and sanitised;
     * it is never stored or served as a file.
     */
    public function createTemplate(): RedirectResponse
    {
        $file = $this->request->getFile('template_file');
        $active = $this->request->getPost('is_active') === '1';
        try {
            if ($file === null || !$file->isValid() || strtolower($file->getClientExtension()) !== 'html' || $file->getSize() > TemplateHtmlSanitizer::MAX_BYTES) {
                throw new FieldException('template_file', lang('Master.fileRules'));
            }
            $source = (string) file_get_contents($file->getTempName());
            $id = (new AuditLogService())->withRequiredAudit(
                $this->currentUserId(),
                fn (): int => (new TemplateAdminService())->create(
                    (string) $this->request->getPost('nama_template'),
                    (string) $this->request->getPost('plan_code'),
                    $active,
                    (string) $this->request->getPost('description'),
                    $source,
                    $this->currentUserId()
                ),
                static fn (int $id): array => ['template.created', 'template', $id, ['nama_template', 'plan_code', 'is_active', 'html'], $active ? 'active' : 'inactive']
            );
        } catch (FieldException $exception) {
            $this->audit('template.created', 'denied', 'template', null, [], 'invalid_input');
            return $this->redirectFieldFailure(self::TEMPLATES_PAGE, $exception);
        } catch (DomainException $exception) {
            $this->audit('template.created', 'denied', 'template', null, [], 'slot');
            return $this->redirectFailure(self::TEMPLATES_PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Template upload failed: {exception}', ['exception' => $exception]);
            $this->audit('template.created', 'failed', 'template', null, [], 'server_error');
            return $this->redirectFailure(self::TEMPLATES_PAGE, $this->withReference(lang('Master.templateSaveFailed')));
        }

        return $this->redirectSuccess(self::TEMPLATES_PAGE, $active ? lang('Master.templateAddedActive') : lang('Master.templateAddedInactive'));
    }

    /**
     * Sample card for the form's live preview: posted HTML is sanitised and rendered with sample
     * data through the same renderer as public cards. Nothing is stored.
     */
    public function previewTemplate(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'no-store')->setHeader('X-Frame-Options', 'SAMEORIGIN')->setHeader('X-Robots-Tag', 'noindex');
        try {
            $html = (new TemplateHtmlSanitizer())->normalize((string) $this->request->getPost('html'));
        } catch (FieldException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['message' => $exception->getMessage()]);
        }

        // A template built on one of the Pro designs previews with that design's look.
        $proDesign = TemplateHtmlSanitizer::proDesign($html);

        return $this->response->setBody(view($proDesign === null ? 'digital_card/custom' : 'digital_card/pro', [
            'segments' => (new TemplateHtmlSanitizer())->segments($html),
            'proDesign' => $proDesign,
            'locale' => $this->request->getLocale(),
        ] + ($proDesign === null ? [] : ['layout' => 'pro-' . $proDesign]) + TemplateSamples::card()));
    }

    /**
     * Active slot limits for Basic and in total (PRD MA-02).
     */
    public function updateSlotLimits(): RedirectResponse
    {
        try {
            $this->auditedMutation('template.limits_changed', 'template_slot_limits', 1, fn () => (new TemplateAdminService())->updateLimits(
                (string) $this->request->getPost('max_active_basic'),
                (string) $this->request->getPost('max_active_total'),
                $this->currentUserId()
            ), ['max_active_basic', 'max_active_total']);
        } catch (FieldException $exception) {
            $this->audit('template.limits_changed', 'denied', 'template_slot_limits', 1, [], 'invalid_input');
            return $this->redirectFieldFailure(self::TEMPLATES_PAGE, $exception);
        } catch (Throwable $exception) {
            log_message('error', 'Slot limit update failed: {exception}', ['exception' => $exception]);
            $this->audit('template.limits_changed', 'failed', 'template_slot_limits', 1, [], 'server_error');
            return $this->redirectFailure(self::TEMPLATES_PAGE, $this->withReference(lang('Master.limitsSaveFailed')));
        }

        return $this->redirectSuccess(self::TEMPLATES_PAGE, lang('Master.limitsSaved'));
    }

    /**
     * Changes a template's display name only (Master Templates "Ubah nama"). The layout, file and
     * cards using the template are untouched.
     */
    public function renameTemplate(int $id): RedirectResponse
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $this->request->getPost('nama_template')));
        try {
            $previous = $this->auditedMutation('template.renamed', 'template', $id, fn (): string => (new TemplateAdminService())->rename($id, $name), ['nama_template']);
        } catch (FieldException $exception) {
            $this->audit('template.renamed', 'denied', 'template', $id, [], 'invalid_input');
            session()->setFlashdata('rename_template', $id);
            return $this->redirectFieldFailure(self::TEMPLATES_PAGE, $exception);
        } catch (DomainException $exception) {
            $this->audit('template.renamed', 'denied', 'template', $id, [], 'missing');
            return $this->redirectFailure(self::TEMPLATES_PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Template rename failed: {exception}', ['exception' => $exception]);
            $this->audit('template.renamed', 'failed', 'template', $id, [], 'server_error');
            return $this->redirectFailure(self::TEMPLATES_PAGE, $this->withReference(lang('Master.renameFailed')));
        }

        return $this->redirectSuccess(self::TEMPLATES_PAGE, lang('Master.renamed', [$previous, $name]));
    }

    public function updateTemplate(int $id): RedirectResponse
    {
        $active = $this->request->getPost('is_active') === '1';
        try {
            $this->auditedMutation('template.status_changed', 'template', $id, fn () => (new TemplateAdminService())->update(
                $id,
                (string) $this->request->getPost('plan_code'),
                $active,
                (int) $this->request->getPost('display_order')
            ), ['plan_code', 'is_active', 'display_order'], $active ? 'active' : 'inactive');
        } catch (DomainException $exception) {
            $this->audit('template.status_changed', 'denied', 'template', $id, [], 'slot_or_input');
            return $this->redirectFailure(self::TEMPLATES_PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Template update failed: {exception}', ['exception' => $exception]);
            $this->audit('template.status_changed', 'failed', 'template', $id, [], 'server_error');
            return $this->redirectFailure(self::TEMPLATES_PAGE, $this->withReference(lang('Master.templateUpdateFailed')));
        }

        return $this->redirectSuccess(self::TEMPLATES_PAGE, $active ? lang('Master.templateEnabled') : lang('Master.templateDisabled'));
    }
}
