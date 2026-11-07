<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\UserModel;
use App\Services\Cards\SubscriptionAccessService;
use App\Services\Users\AccountStatus;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;

/**
 * Identity check on every dashboard request: the account must still exist with a known role, not be
 * suspended, and match the session version recorded at sign-in. Suspending an account, changing its
 * password or role bumps that version, which ends every older session on its next request.
 * The role is re-read from the database so changes take effect without a new login.
 * An expired User stays signed in (PRD EX-01); the `subscription` filter limits what they can use.
 */
class Auth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (! $session->get('logged_in') || ! (int) $session->get('id_user')) {
            // Remember where a signed-out visitor was going (e.g. a link in an email), so login can
            // continue there. Only dashboard GET pages; the controller checks ownership as usual.
            if (strtoupper($request->getMethod()) === 'GET') {
                $path  = '/' . ltrim($request->getUri()->getPath(), '/');
                $query = $request->getUri()->getQuery();
                if (preg_match('#^/dashboard(/[A-Za-z0-9_\-/.]*)?$#', $path) === 1 && ! str_contains($path, '..')) {
                    $session->set('after_login', $path . ($query !== '' ? '?' . $query : ''));
                }
            }

            return redirect()->to('/login');
        }

        $user = (new UserModel())->find((int) $session->get('id_user'));
        if (! $user || ! in_array($user['access_level'], ['User', 'Admin', 'Super Admin'], true)) {
            $session->destroy();

            return redirect()->to('/login');
        }

        // Sessions created before session versions existed carry none; adopt the current version once
        // so deploying this does not sign everyone out. Suspension is still enforced below.
        if ($session->get('session_version') === null && ! AccountStatus::isSuspended($user)) {
            $session->set('session_version', AccountStatus::sessionVersion($user));
        }

        if (! AccountStatus::sessionIsCurrent($user, $session->get('session_version'))) {
            $message = lang(AccountStatus::isSuspended($user) ? 'Auth.errSuspended' : 'Auth.sessionEnded');
            // End this session: drop its data and move to a new ID (the old ID is deleted), but keep a
            // live session so the notice reaches the login page's existing `msg` alert.
            $_SESSION = [];
            $session->regenerate(true);
            $session->setFlashdata('msg', $message);

            return redirect()->to('/login');
        }

        $session->set('access_level', $user['access_level']);
        $session->set('username', $user['username']);
        $session->set('card_active', (new SubscriptionAccessService())->isActive($user, new DateTimeImmutable('now')));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
