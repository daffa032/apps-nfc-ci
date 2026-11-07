<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Analytics\CardAnalyticsService;
use App\Services\Cards\DashboardService;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;

class Dashboard extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    protected $helpers = ['asset', 'feedback', 'date'];

    public function index(): string|RedirectResponse
    {
        $summary = (new DashboardService())->summaryFor($this->currentUserId(), new DateTimeImmutable('now'));
        if ($summary === null) {
            return redirect()->to('/login');
        }

        $now = new DateTimeImmutable('now');
        $analytics = new CardAnalyticsService();
        $account = ['id_user' => $this->currentUserId(), 'access_level' => $this->currentRole()];
        // Same aggregate as the Statistik page (PRD AN-02); Basic and expired accounts see no numbers.
        $stats = $summary['isActive'] && $analytics->isPro($account, $now) ? $analytics->month($this->currentUserId(), null, $now) : null;

        return view('dashboard/pages/main/dashboard', $this->dashboardShellData() + $summary + [
            'stats' => $stats,
            'feedback' => $this->pendingFeedback(),
        ]);
    }
}
