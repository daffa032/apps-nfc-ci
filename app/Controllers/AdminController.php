<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\FieldException;
use App\Services\Users\AdminManagementService;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

class AdminController extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    private const ADMIN_TABLE = 'dashboard/admin_table';

    protected $helpers = ['asset', 'feedback'];

    private function render(string $view, array $data = []): string
    {
        return view($view, $this->dashboardShellData() + $data + ['feedback' => $this->pendingFeedback(), 'fieldErrors' => $this->pendingFieldErrors()]);
    }

    public function addAdminForm(): string
    {
        return $this->render('dashboard/pages/add_admin/add_admin');
    }

    public function addAdmin(): RedirectResponse
    {
        try {
            (new AdminManagementService())->create((array) $this->request->getPost());
        } catch (DomainException $exception) {
            return $exception instanceof FieldException
                ? $this->redirectFieldFailure('dashboard/add_admin', $exception)
                : $this->redirectFailureWithoutSecrets('dashboard/add_admin', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Admin creation failed: {exception}', ['exception' => $exception]);
            $this->audit('admin.created', 'failed', 'admin', null, [], 'server_error');

            return $this->redirectFailureWithoutSecrets('dashboard/add_admin', $this->withReference(lang('Accounts.adminAddFailed')));
        }

        $this->audit('admin.created', 'success', 'admin', null, ['username', 'email', 'access_level'], (string) $this->request->getPost('access_level'));

        return $this->redirectSuccess(self::ADMIN_TABLE, lang('Accounts.adminAdded'));
    }

    public function listAdmins(): string
    {
        return $this->render('dashboard/pages/admin_table/admin_table', [
            'users'         => (new AdminManagementService())->list(),
            'currentUserId' => $this->currentUserId(),
        ]);
    }

    public function editAdmin(int $id_user): RedirectResponse|string
    {
        if ($id_user === $this->currentUserId()) {
            return redirect()->to('dashboard/setting/' . $this->dashboardShellData()['encryptedId']);
        }

        try {
            $user = (new AdminManagementService())->find($id_user);
        } catch (DomainException $exception) {
            return $this->redirectFailure(self::ADMIN_TABLE, $exception->getMessage());
        }

        return $this->render('dashboard/pages/admin_table/edit_admin', ['user' => $user]);
    }

    public function updateAdmin(int $id_user): RedirectResponse
    {
        try {
            (new AdminManagementService())->update($id_user, $this->currentUserId(), (array) $this->request->getPost());
        } catch (DomainException $exception) {
            return $exception instanceof FieldException
                ? $this->redirectFieldFailure('dashboard/admin_table/edit_admin/' . $id_user, $exception)
                : $this->redirectFailureWithoutSecrets('dashboard/admin_table/edit_admin/' . $id_user, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Admin update failed: {exception}', ['exception' => $exception]);
            $this->audit('admin.updated', 'failed', 'admin', $id_user, [], 'server_error');

            return $this->redirectFailureWithoutSecrets('dashboard/admin_table/edit_admin/' . $id_user, $this->withReference(lang('Accounts.adminUpdateFailed')));
        }

        $fields = ['username', 'email', 'access_level'];
        if ((string) $this->request->getPost('password') !== '') {
            $fields[] = 'password';
        }
        $this->audit('admin.updated', 'success', 'admin', $id_user, $fields);

        return $this->redirectSuccess(self::ADMIN_TABLE, lang('Accounts.adminUpdated'));
    }

    public function deleteAdmin(int $id_user): RedirectResponse
    {
        try {
            $this->auditedMutation('admin.deleted', 'admin', $id_user, fn () => (new AdminManagementService())->delete($id_user, $this->currentUserId()));
        } catch (DomainException $exception) {
            $this->audit('admin.deleted', 'denied', 'admin', $id_user, [], 'rule');

            return $this->redirectFailure(self::ADMIN_TABLE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Admin deletion failed: {exception}', ['exception' => $exception]);
            $this->audit('admin.deleted', 'failed', 'admin', $id_user, [], 'server_error');

            return $this->redirectFailure(self::ADMIN_TABLE, $this->withReference(lang('Accounts.adminDeleteFailed')));
        }

        return $this->redirectSuccess(self::ADMIN_TABLE, lang('Accounts.adminDeleted'));
    }
}
