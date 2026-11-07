<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\LoginService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;

class Auth_login extends BaseController
{
    public function loginPost(): RedirectResponse
    {
        $session = session();

        try {
            $values = (new LoginService())->attempt(
                trim((string) $this->request->getPost('username')),
                (string) $this->request->getPost('password'),
            );
        } catch (DomainException $exception) {
            // No username, password or account ID is stored for failed attempts.
            (new AuditLogService())->record('auth.login_failed', 'denied', null, null, null, [], null, 'anonymous');
            // The login view shows `msg` in its existing alert.
            $session->setFlashdata('msg', $exception->getMessage());

            return redirect()->to('/login');
        }

        $target = self::safeTarget($session->get('after_login'));
        $session->regenerate(true);
        $session->remove('after_login');
        $session->set($values);
        (new AuditLogService())->record('auth.login', 'success', $values['id_user'], 'account', $values['id_user']);

        $redirect = redirect()->to($target);
        // The account's saved language follows the user to a new device or browser.
        $saved  = (string) ((new UserModel())->find((int) $values['id_user'])['preferred_locale'] ?? '');
        $config = config('Locale');
        if (in_array($saved, $config->supported, true)) {
            $redirect->setCookie($config->cookieName, $saved, 365 * 86400, '', '/', '', null, false, 'Lax');
        }

        return $redirect;
    }

    /**
     * Page remembered by the auth filter, or the dashboard. Only a local /dashboard path is used,
     * so a crafted value can never send the user to another site.
     */
    private static function safeTarget(mixed $value): string
    {
        $value = is_string($value) ? $value : '';
        $path  = (string) parse_url($value, PHP_URL_PATH);

        return preg_match('#^/dashboard(/[A-Za-z0-9_\-/.]*)?$#', $path) === 1 && ! str_contains($value, '..') && ! str_contains($value, '//')
            ? $value
            : '/dashboard';
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to('/login');
    }
}
