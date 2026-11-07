<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Users\AccountStatusService;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

class AccountStatusController extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    public function suspend(int $id): RedirectResponse
    {
        return $this->change($id, true);
    }

    public function restore(int $id): RedirectResponse
    {
        return $this->change($id, false);
    }

    private function change(int $id, bool $suspend): RedirectResponse
    {
        $back = $this->backTo();
        $action = $suspend ? 'account.suspended' : 'account.restored';
        try {
            $service = new AccountStatusService();
            $this->auditedMutation($action, 'account', $id, fn () => $suspend
                ? $service->suspend($this->currentUserId(), $this->currentRole(), $id)
                : $service->restore($this->currentUserId(), $this->currentRole(), $id), ['account_status']);
        } catch (DomainException $exception) {
            $this->audit($action, 'denied', 'account', $id, [], 'rule');
            return $this->redirectFailure($back, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Account status change failed: {exception}', ['exception' => $exception]);
            $this->audit($action, 'failed', 'account', $id, [], 'server_error');
            return $this->redirectFailure($back, $this->withReference(lang('Accounts.statusFailed')));
        }

        return $this->redirectSuccess($back, $suspend ? lang('Accounts.suspendedDone') : lang('Accounts.restoredDone'));
    }

    /** Only the two management tables are valid return targets; anything else falls back to the user table. */
    private function backTo(): string
    {
        return $this->request->getPost('return') === 'admin_table' ? 'dashboard/admin_table' : 'dashboard/user_table';
    }
}
