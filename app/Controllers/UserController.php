<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\FieldException;
use App\Services\Users\UserManagementService;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

class UserController extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    private const USER_TABLE = '/dashboard/user_table';

    protected $helpers = ['asset', 'feedback', 'date'];

    private function service(): UserManagementService
    {
        return new UserManagementService();
    }

    private function render(string $view, array $data = []): string
    {
        return view($view, $this->dashboardShellData() + $data + ['feedback' => $this->pendingFeedback(), 'fieldErrors' => $this->pendingFieldErrors()]);
    }

    public function addUserForm(): string
    {
        return $this->render('dashboard/pages/add_user/add_user');
    }

    public function listUsers(): string
    {
        return $this->render('dashboard/pages/user_table/user_table', [
            'users'         => $this->service()->list(),
            'nowUtc'        => gmdate('Y-m-d H:i:s'),
            'currentUserId' => $this->currentUserId(),
        ]);
    }

    public function addUser(): RedirectResponse
    {
        try {
            $this->service()->create((array) $this->request->getPost());
            $this->audit('user.created', 'success', 'user', null, ['username', 'email']);

            return $this->redirectSuccess(self::USER_TABLE, lang('Accounts.userAdded'));
        } catch (DomainException $exception) {
            return $exception instanceof FieldException
                ? $this->redirectFieldFailure('/dashboard/add_user', $exception)
                : $this->redirectFailureWithoutSecrets('/dashboard/add_user', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Unable to create user: {exception}', ['exception' => $exception]);
            $this->audit('user.created', 'failed', 'user', null, [], 'server_error');

            return $this->redirectFailureWithoutSecrets('/dashboard/add_user', $this->withReference(lang('Accounts.userAddFailed')));
        }
    }

    public function editUser(int $id): RedirectResponse|string
    {
        try {
            $user = $this->service()->find($id);
        } catch (DomainException $exception) {
            return $this->redirectFailure(self::USER_TABLE, $exception->getMessage());
        }

        return $this->render('dashboard/pages/user_table/edit_user', ['user' => $user]);
    }

    public function updateUser(int $id): RedirectResponse
    {
        try {
            $this->service()->update($id, (array) $this->request->getPost());
            $this->audit('user.updated', 'success', 'user', $id, $this->submittedAccountFields());

            return $this->redirectSuccess(self::USER_TABLE, lang('Accounts.userUpdated'));
        } catch (DomainException $exception) {
            return $exception instanceof FieldException
                ? $this->redirectFieldFailure('/dashboard/user_table/edit_user/' . $id, $exception)
                : $this->redirectFailureWithoutSecrets('/dashboard/user_table/edit_user/' . $id, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Unable to update user: {exception}', ['exception' => $exception]);
            $this->audit('user.updated', 'failed', 'user', $id, [], 'server_error');

            return $this->redirectFailureWithoutSecrets('/dashboard/user_table/edit_user/' . $id, $this->withReference(lang('Accounts.userUpdateFailed')));
        }
    }

    public function deleteUser(int $id): RedirectResponse
    {
        try {
            // Deletion is permanent (LOG-13 decision), so it only happens if the audit row is kept.
            $this->auditedMutation('user.deleted', 'user', $id, fn () => $this->service()->delete($id));

            return $this->redirectSuccess(self::USER_TABLE, lang('Accounts.userDeleted'));
        } catch (DomainException $exception) {
            return $this->redirectFailure(self::USER_TABLE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Unable to delete user: {exception}', ['exception' => $exception]);
            $this->audit('user.deleted', 'failed', 'user', $id, [], 'server_error');

            return $this->redirectFailure(self::USER_TABLE, $this->withReference(lang('Accounts.userDeleteFailed')));
        }
    }

    public function activateUser(): RedirectResponse
    {
        return $this->updateAccess((string) $this->request->getPost('expiration_date'));
    }

    public function addActivateUser(): RedirectResponse
    {
        return $this->updateAccess((string) $this->request->getPost('expiration_date'));
    }

    public function deleteUserAccess(int $id): RedirectResponse
    {
        return $this->updateAccess(null, $id);
    }

    private function updateAccess(?string $duration, ?int $id = null): RedirectResponse
    {
        $id ??= (int) $this->request->getPost('id_user');
        $action = $duration === null ? 'subscription.revoked' : 'subscription.granted';
        if ($this->currentRole() !== 'Super Admin') {
            $this->audit($action, 'denied', 'user', $id ?: null, [], 'not_super_admin');

            return $this->redirectFailure('/dashboard', lang('Accounts.accessDenied'));
        }

        if ($id < 1) {
            return $this->redirectFailure(self::USER_TABLE, lang('Accounts.userInvalid'));
        }

        try {
            $reason = trim((string) $this->request->getPost('reason'));
            // Reason text stays in subscription_grants; the audit row keeps the duration code only.
            $this->auditedMutation($action, 'user', $id, fn () => $this->service()->changeAccess($id, $duration, $this->currentUserId(), null, $reason), [], $duration);

            return $this->redirectSuccess(self::USER_TABLE, lang('Accounts.validityUpdated'));
        } catch (DomainException $exception) {
            return $this->redirectFailure(self::USER_TABLE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Unable to change user access: {exception}', ['exception' => $exception]);
            $this->audit($action, 'failed', 'user', $id, [], 'server_error');

            return $this->redirectFailure(self::USER_TABLE, $this->withReference(lang('Accounts.validityFailed')));
        }
    }

    /**
     * Names (never values) of the account fields an edit form submitted with content.
     *
     * @return list<string>
     */
    private function submittedAccountFields(): array
    {
        $fields = ['username', 'email'];
        if ((string) $this->request->getPost('password') !== '') {
            $fields[] = 'password';
        }
        if ($this->request->getPost('access_level') !== null) {
            $fields[] = 'access_level';
        }

        return $fields;
    }
}
