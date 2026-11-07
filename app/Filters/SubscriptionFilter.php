<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Card features (profile, gallery, social links, templates) need an active card.
 * Runs after `auth`, which stores the result of SubscriptionAccessService for this request.
 * Expired owners are sent to the billing page; AJAX endpoints get a 403 JSON instead.
 */
class SubscriptionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('card_active') === true) {
            return null;
        }

        $message = 'Masa aktif kartu sudah habis. Perpanjang untuk menggunakan fitur ini.';
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(403)->setJSON([
                'success' => false,
                'status'  => 'error',
                'message' => $message,
            ]);
        }

        return redirect()->to('/dashboard/billing')->with('error', $message);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
