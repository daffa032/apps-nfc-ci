<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\FieldException;
use App\Services\Auth\PasswordResetService;
use App\Services\Cards\DashboardService;
use App\Services\Mail\AppMailer;
use App\Services\Users\AccountSettingsService;
use App\Services\Users\AccountStatus;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;
use DomainException;
use RuntimeException;
use Throwable;

class ResetPasswordPenggunaController extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    public function edit_userPengguna(string $encryptedId): RedirectResponse|string
    {
        helper(['asset', 'feedback', 'date']);
        $id = $this->accountId($encryptedId);
        if ($id === null) {
            return $this->redirectFailure('dashboard', lang('Settings.notAllowed'));
        }

        $user = (new AccountSettingsService())->find($id);
        if (! $user) {
            return $this->redirectFailure('dashboard', lang('Settings.accountMissing'));
        }

        $summary = (new DashboardService())->summaryFor($id, new DateTimeImmutable('now'));

        return view('dashboard/pages/edit_personal/edit_password', $this->dashboardShellData() + [
            'user'        => $user,
            'planCode'    => $summary['planCode'] ?? 'basic',
            'planLabel'   => $summary['planLabel'] ?? 'Basic',
            'expiryLabel' => $summary['expiryLabel'] ?? '-',
            'feedback'    => $this->pendingFeedback(),
            'fieldErrors' => $this->pendingFieldErrors(),
        ]);
    }

    public function update_userPengguna(string $encryptedId): RedirectResponse
    {
        $id = $this->accountId($encryptedId);
        if ($id === null) {
            $this->audit('account.settings_updated', 'denied', 'account', null, [], 'not_owner');

            return $this->redirectFailure('dashboard', lang('Settings.notAllowed'));
        }

        $settingsPage = 'dashboard/setting/' . $encryptedId;

        try {
            (new AccountSettingsService())->update($id, (array) $this->request->getPost());
        } catch (DomainException $exception) {
            return $exception instanceof FieldException
                ? $this->redirectFieldFailure($settingsPage, $exception)
                : $this->redirectFailureWithoutSecrets($settingsPage, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Account settings update failed: {exception}', ['exception' => $exception]);

            return $this->redirectFailureWithoutSecrets($settingsPage, lang('Settings.saveFailed'));
        }

        $fields = [];
        if ((string) $this->request->getPost('password') !== '') {
            $fields[] = 'password';
        }
        $fields[] = 'email';
        $this->audit('account.settings_updated', 'success', 'account', $id, $fields);

        // Changing the password bumped session_version, ending other sessions; keep this one.
        $account = (new AccountSettingsService())->find($id);
        if ($account !== null) {
            session()->set('session_version', AccountStatus::sessionVersion($account));
        }

        return $this->redirectSuccess($settingsPage, lang('Settings.saved'));
    }

    public function forgotPassword()
    {
        return view('login/layouts/login-home', ['page' => 'login/pages/forgotpassword/forgot_password']);
    }

    public function resetPasswordSent()
    {
        return view('login/layouts/login-home', ['page' => 'login/pages/forgotpassword/forgot_password_sent']);
    }

    public function sendResetLink()
    {
        $email = trim((string) $this->request->getPost('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', lang('Auth.errEmail'));
        }

        // Rate limit per IP and per email address (shared cache). Same message either way so it
        // does not reveal whether the address belongs to an account.
        $throttler = service('throttler');
        $ipKey     = 'reset-ip-' . hash('sha256', (string) $this->request->getIPAddress());
        $emailKey  = 'reset-email-' . hash('sha256', strtolower($email));
        if (! $throttler->check($ipKey, 10, HOUR) || ! $throttler->check($emailKey, 3, HOUR)) {
            return redirect()->back()->with('error', lang('Auth.errTooMany'));
        }

        $resets = new PasswordResetService();
        $token  = null;

        try {
            $token = $resets->issue($email);
            if ($token !== null) {
                $mailer = new AppMailer();
                if (! $mailer->isConfigured()) {
                    throw new RuntimeException('SMTP is not configured.');
                }
                $sent = $mailer->send($email, lang('Email.resetSubject'), 'password_reset', [
                    'resetUrl' => base_url('forgot-password/reset/' . $token),
                    'minutes'  => 30,
                ]);
                if (! $sent) {
                    throw new RuntimeException('Password reset email could not be sent.');
                }
            }
        } catch (Throwable $exception) {
            if ($token !== null) {
                try {
                    $resets->invalidate($token);
                } catch (Throwable $cleanupError) {
                    log_message('error', 'Password reset token cleanup failed: {exception}', ['exception' => $cleanupError]);
                }
            }
            log_message('error', 'Password reset request failed: {exception}', ['exception' => $exception]);

            return redirect()->back()->with('error', lang('Auth.errSendFailed'));
        }

        return redirect()->to('forgot_password/link-success');
    }

    public function reset(string $token)
    {
        if (! (new PasswordResetService())->isValid($token)) {
            return redirect()->to('forgot_password')->with('error', lang('Auth.errLinkInvalid'));
        }

        return view('login/layouts/login-home', [
            'page'  => 'login/pages/reset_password/reset_password',
            'token' => $token,
        ]);
    }

    public function updatePassword()
    {
        try {
            (new PasswordResetService())->reset(
                (string) $this->request->getPost('token'),
                (string) $this->request->getPost('password'),
                (string) $this->request->getPost('repeat_password'),
            );
        } catch (DomainException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Password reset failed: {exception}', ['exception' => $exception]);

            return redirect()->back()->with('error', lang('Auth.errResetFailed'));
        }

        return redirect()->to('login')->with('success', lang('Auth.resetDone'));
    }

    private function accountId(string $encoded): ?int
    {
        if (! preg_match('/\A[A-Za-z0-9_-]+\z/D', $encoded)) {
            return null;
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($decoded === false || ! ctype_digit($decoded)) {
            return null;
        }
        $id = (int) $decoded;

        return $id > 0 && $id === (int) session()->get('id_user') ? $id : null;
    }
}
