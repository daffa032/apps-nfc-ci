<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Cards\MissingProfileException;
use App\Services\Cards\PlanRequiredException;
use App\Services\Cards\PublicCardService;
use App\Services\Cards\TemplateSelectionService;
use App\Traits\RendersCardPages;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

class TemplatesController extends BaseController
{
    use RendersCardPages;
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    private const PROFILE_FORM = 'dashboard/personal_info_form';
    private const TEMPLATES_PAGE = 'dashboard/templates';

    protected $helpers = ['asset', 'feedback'];

    public function templatesPage(): string|RedirectResponse
    {
        $page = (new TemplateSelectionService())->pageFor($this->currentUserId());
        if ($page === null) {
            return $this->redirectFailure(self::PROFILE_FORM, lang('Templates.needProfile'));
        }

        return view('dashboard/pages/templates/templates_page', $this->dashboardShellData() + $page + [
            'feedback' => $this->pendingFeedback(),
        ]);
    }

    /**
     * The owner's card in another layout, loaded into the preview dialog's iframe. Not a visit:
     * no analytics, no click tracking, never cached, never indexed, only framed by this site.
     */
    public function preview(int $templateId): string
    {
        $preview = (new TemplateSelectionService())->previewFor($this->currentUserId(), $templateId, new PublicCardService());
        if ($preview === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->response
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Frame-Options', 'SAMEORIGIN')
            ->setHeader('X-Robots-Tag', 'noindex');

        return $this->renderCardPage($preview['view'], $preview['data']);
    }

    public function selectTemplate(): RedirectResponse
    {
        $templateId = filter_var($this->request->getPost('id_template'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        try {
            (new TemplateSelectionService())->select($this->currentUserId(), $templateId === false ? 0 : $templateId);
        } catch (MissingProfileException $exception) {
            return $this->redirectFailure(self::PROFILE_FORM, $exception->getMessage());
        } catch (PlanRequiredException $exception) {
            $this->audit('theme.selected', 'denied', 'template', $templateId === false ? null : $templateId, [], 'plan_required');
            return $this->redirectFailure(self::TEMPLATES_PAGE, $exception->getMessage());
        } catch (DomainException $exception) {
            $this->audit('theme.selected', 'denied', 'template', $templateId === false ? null : $templateId, [], 'invalid_template');
            return $this->redirectFailure(self::TEMPLATES_PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Template selection failed: {exception}', ['exception' => $exception]);
            return $this->redirectFailure(self::TEMPLATES_PAGE, lang('Templates.selectFailed'));
        }

        $this->audit('theme.selected', 'success', 'template', $templateId === false ? null : $templateId);
        return $this->redirectSuccess(self::TEMPLATES_PAGE, lang('Templates.selected'));
    }
}
