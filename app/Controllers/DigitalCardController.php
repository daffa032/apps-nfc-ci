<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Analytics\CardAnalyticsService;
use App\Services\Cards\PublicCardService;
use App\Traits\RendersCardPages;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;

class DigitalCardController extends BaseController
{
    use RendersCardPages;

    public function digitalCardProfile(string $username): string|RedirectResponse
    {
        $now = new DateTimeImmutable('now');
        $card = (new PublicCardService())->forUsername($username, $now);

        // Notices never show profile data and must not be cached (PRD EX-01).
        return match ($card['state']) {
            PublicCardService::SHOW => $this->show($card, $now),
            // Old username after a rename: permanent redirect, the target page decides what to show.
            PublicCardService::MOVED => redirect()->to(base_url(rawurlencode($card['username'])), 301),
            PublicCardService::SUSPENDED => $this->notice(410, 'digital_card/suspended'),
            PublicCardService::INACTIVE => $this->notice(403, 'digital_card/inactive'),
            default => throw PageNotFoundException::forPageNotFound(),
        };
    }

    /**
     * Pro share page: large QR of the canonical card URL, copy/share, save image (PRD QR-02/03).
     * Not counted as a card view. Basic, suspended and expired cards get no QR or profile data.
     */
    public function share(string $username): string|RedirectResponse
    {
        $result = (new PublicCardService())->shareFor($username, new DateTimeImmutable('now'));

        return match ($result['state']) {
            PublicCardService::SHOW => $this->sharePage($result['data']),
            PublicCardService::MOVED => redirect()->to(base_url(rawurlencode($result['username']) . '/share'), 301),
            PublicCardService::SUSPENDED => $this->notice(410, 'digital_card/suspended'),
            PublicCardService::INACTIVE => $this->notice(403, 'digital_card/inactive'),
            default => throw PageNotFoundException::forPageNotFound(),
        };
    }

    private function sharePage(array $data): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store')->setHeader('X-Robots-Tag', 'noindex');

        return $this->renderCardPage('digital_card/share', $data);
    }

    private function show(array $card, DateTimeImmutable $now): string
    {
        $user = $card['data']['user'];
        $analytics = new CardAnalyticsService();
        $analytics->recordView($user, [
            'method' => $this->request->getMethod(),
            'ip' => $this->request->getIPAddress(),
            'user_agent' => (string) $this->request->getUserAgent(),
        ] + $this->viewer(), $now);

        $token = $analytics->isPro($user, $now) ? $analytics->eventToken((int) $user['id_user'], $now) : null;

        // Floating QR/share button for Pro cards (PRD QR-01); Basic keeps the share-link button.
        $shareQrUrl = $analytics->isPro($user, $now) ? base_url(rawurlencode((string) $user['username']) . '/share') : null;

        return $this->renderCardPage($card['view'], $card['data'] + [
            'shareQrUrl' => $shareQrUrl,
            'eventUrl' => $token === null ? null : base_url('cards/' . rawurlencode((string) $user['username']) . '/events'),
            'eventToken' => $token,
        ]);
    }

    /**
     * Who is looking, so owner and staff previews are not counted. The session is only opened
     * when the visitor already has a session cookie, so anonymous visitors get no cookie.
     *
     * @return array{viewer_id: ?int, viewer_role: ?string}
     */
    private function viewer(): array
    {
        if ($this->request->getCookie(config('Session')->cookieName) === null) {
            return ['viewer_id' => null, 'viewer_role' => null];
        }
        $session = session();
        $id = (int) $session->get('id_user');

        return ['viewer_id' => $id > 0 ? $id : null, 'viewer_role' => $id > 0 ? (string) $session->get('access_level') : null];
    }

    private function notice(int $status, string $view): string
    {
        $this->response->setStatusCode($status)->setHeader('Cache-Control', 'private, no-store');

        // Notices contain no card data and may follow the visitor's interface language.
        return view($view, ['locale' => $this->request->getLocale(), 'langSwitch' => false]);
    }
}
