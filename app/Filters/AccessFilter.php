<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\Audit\AuditLogService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $role    = session()->get('access_level');
        $allowed = $arguments ?: [];

        if ($role === null || ! in_array($role, $allowed, true)) {
            (new AuditLogService())->record('auth.access_denied', 'denied', (int) session()->get('id_user') ?: null, 'route', substr($request->getUri()->getPath(), 0, 32), [], 'role');

            return redirect()->to('/dashboard')->with('error', 'Akses ditolak.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
