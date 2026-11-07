<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AccountRepository;
use App\Services\Analytics\CardAnalyticsService;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;

/**
 * "Statistik" (PRD AN-01, AN-02): the owner's own card only, Pro only. Basic sees what the
 * feature does, never invented numbers. The page and the Dashboard read the same aggregate.
 */
class AnalyticsController extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    protected $helpers = ['asset', 'feedback', 'date'];

    public function index(): RedirectResponse|string
    {
        $account = (new AccountRepository())->find($this->currentUserId());
        if (! $account) {
            return redirect()->to('/login');
        }

        $now       = new DateTimeImmutable('now');
        $analytics = new CardAnalyticsService();
        $isPro     = $analytics->isPro($account, $now);
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/pages/analytics/analytics', $this->dashboardShellData() + [
            'isPro'    => $isPro,
            'stats'    => $isPro ? $analytics->month($this->currentUserId(), $this->monthParam(), $now) : null,
            'feedback' => $this->pendingFeedback(),
        ]);
    }

    public function data(): ResponseInterface
    {
        $account   = (new AccountRepository())->find($this->currentUserId());
        $now       = new DateTimeImmutable('now');
        $analytics = new CardAnalyticsService();
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if (! $account || ! $analytics->isPro($account, $now)) {
            return $this->response->setStatusCode(403)->setJSON(['message' => lang('Analytics.proOnly')]);
        }

        $stats = $analytics->month($this->currentUserId(), $this->monthParam(), $now);
        unset($stats['months']);

        return $this->response->setJSON($stats);
    }

    private function monthParam(): ?string
    {
        $month = $this->request->getGet('month');

        return is_string($month) ? $month : null;
    }
}
