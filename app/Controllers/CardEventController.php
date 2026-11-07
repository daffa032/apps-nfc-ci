<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Analytics\CardAnalyticsService;
use App\Services\Cards\PublicCardService;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;

/**
 * POST /cards/{username}/events: a contact click (WhatsApp/phone/email) on a public card.
 * Anonymous, so CSRF does not apply; instead the event needs the card's daily token, an
 * allowlisted type, a same-origin request and a rate limit. Always answers 204 so the response
 * reveals nothing about the card or whether the click was counted.
 */
class CardEventController extends BaseController
{
    public function store(string $username): ResponseInterface
    {
        $this->response->setStatusCode(204)->setHeader('Cache-Control', 'no-store');

        $origin = (string) $this->request->getHeaderLine('Origin');
        $base = rtrim((string) config('App')->baseURL, '/');
        if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url($base, PHP_URL_HOST)) {
            return $this->response;
        }
        $throttleKey = 'card_event_' . hash('sha256', $this->request->getIPAddress() . '|' . strtolower($username));
        if (!service('throttler')->check($throttleKey, 30, MINUTE)) {
            return $this->response;
        }

        $now = new DateTimeImmutable('now');
        $card = (new PublicCardService())->forUsername($username, $now);
        if ($card['state'] !== PublicCardService::SHOW) {
            return $this->response;
        }

        (new CardAnalyticsService())->recordContactClick(
            $card['data']['user'],
            (string) $this->request->getPost('type'),
            (string) $this->request->getPost('token'),
            (string) $this->request->getUserAgent(),
            $now
        );

        return $this->response;
    }
}
