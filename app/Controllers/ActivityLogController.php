<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Audit\AuditLogService;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;

/**
 * Read-only Activity Logs for Super Admin (PRD AUD-02). The route filter enforces the role;
 * there is no edit or delete endpoint for audit events.
 */
class ActivityLogController extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    protected $helpers = ['asset', 'feedback', 'date'];

    public function index(): string
    {
        $input = (array) $this->request->getGet();
        $logs  = (new AuditLogService())->search($input, (int) ($input['page'] ?? 1));
        // Reading the log is itself recorded, without the search terms.
        $this->audit('audit.viewed', 'success', 'audit');

        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/pages/activity_logs/activity_logs', $this->dashboardShellData() + $logs + [
            // The constants are the allowlist; labels follow the interface language.
            'actions'  => array_combine(array_keys(AuditLogService::ACTIONS), array_map(static fn (string $code): string => lang('Audit.action_' . str_replace('.', '_', $code)), array_keys(AuditLogService::ACTIONS))),
            'outcomes' => array_combine(array_keys(AuditLogService::OUTCOMES), array_map(static fn (string $code): string => lang('Audit.outcome_' . $code), array_keys(AuditLogService::OUTCOMES))),
            'feedback' => $this->pendingFeedback(),
        ]);
    }
}
