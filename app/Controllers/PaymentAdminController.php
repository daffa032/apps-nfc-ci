<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PaymentOrderRepository;
use App\Services\Payments\ApplyPaymentService;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Payments monitor for Admin and Super Admin (PRD AD-01). Read-only plus a rate-limited, verified
 * status check against Midtrans. There is deliberately no "mark as paid": manual access stays a
 * separate, audited grant that is never counted as revenue (AD-02). A Super Admin can resolve an
 * order flagged "needs review" (grant its period, revoke it after a refund, or dismiss the flag),
 * always with a reason; the route group restricts resolve() to Super Admin.
 */
class PaymentAdminController extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    private const STATUSES = ['creating', 'pending', 'checking', 'paid', 'failed', 'cancelled', 'expired', 'refunded'];

    protected $helpers = ['asset', 'feedback', 'date'];

    public function index(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/pages/payments/payments', $this->dashboardShellData() + $this->listData() + [
            'statuses' => self::STATUSES,
            'feedback' => $this->pendingFeedback(),
        ]);
    }

    /**
     * GET (JSON): live view of the list. The page sends the marker it already shows (?since=);
     * when nothing changed the answer is tiny and no list query or view runs (the usual case).
     * Otherwise the same summary, attention list and table as index() for the current filters and
     * page come back as HTML fragments. Also returns a fresh CSRF hash for the sync POST.
     */
    public function live(): ResponseInterface
    {
        // Polling must not hold the session file lock (other tabs of the same admin would wait).
        session()->close();
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $marker = (new PaymentOrderRepository())->changeMarker();
        $base = ['marker' => $marker, 'as_of' => gmdate('Y-m-d\TH:i:s\Z'), 'csrf' => csrf_hash()];
        if ((string) $this->request->getGet('since') === $marker) {
            return $this->response->setJSON($base + ['changed' => false]);
        }

        helper('date');
        $data = $this->listData();

        return $this->response->setJSON($base + [
            'changed' => true,
            'html' => [
                'summary' => view('dashboard/pages/payments/_summary', $data, ['debug' => false]),
                'attention' => view('dashboard/pages/payments/_attention', $data, ['debug' => false]),
                'table' => view('dashboard/pages/payments/_table', $data, ['debug' => false]),
            ],
        ]);
    }

    /**
     * POST (JSON): background status check for open orders, so the list updates without the
     * "check with Midtrans" button even when notifications cannot reach this server. Fallback
     * only: webhooks and the reconcile cron stay the main path. At most once a minute for all
     * admins together (shared cache lock), at most 5 orders and about 8 seconds of gateway time,
     * each through the same locked, exactly-once path as the button and reconcile.
     */
    public function sync(): ResponseInterface
    {
        session()->close();
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if (!config('Midtrans')->isConfigured()) {
            return $this->response->setJSON(['checked' => 0, 'skipped' => 'gateway_off']);
        }
        $cache = cache();
        if ($cache->get('payments_live_sync') !== null) {
            return $this->response->setJSON(['checked' => 0, 'skipped' => 'recent']);
        }
        $cache->save('payments_live_sync', 1, 60);

        $repo = new PaymentOrderRepository();
        $service = new ApplyPaymentService();
        $checked = 0;
        $changed = 0;
        $started = microtime(true);
        foreach ($repo->openForLiveSync(gmdate('Y-m-d H:i:s'), 5) as $order) {
            if (microtime(true) - $started > 8) {
                break;
            }
            try {
                $outcome = $service->refresh($order, new DateTimeImmutable('now'));
                $checked++;
                $changed += in_array($outcome, [ApplyPaymentService::OUTCOME_APPLIED, ApplyPaymentService::OUTCOME_UPDATED, ApplyPaymentService::OUTCOME_REVIEW, ApplyPaymentService::OUTCOME_REFUNDED], true) ? 1 : 0;
            } catch (Throwable $exception) {
                log_message('warning', 'Live sync failed for {order}: {message}', ['order' => $order['order_code'], 'message' => $exception->getMessage()]);
            }
        }

        // The minute starts when this run ends, so a slow gateway never lets runs overlap.
        $cache->save('payments_live_sync', 1, 60);

        return $this->response->setJSON(['checked' => $checked, 'changed' => $changed]);
    }

    /**
     * Filters, rows, summary and attention list shared by the page and its live endpoint.
     *
     * @return array<string, mixed>
     */
    private function listData(): array
    {
        $input = (array) $this->request->getGet();
        $filters = [
            'status' => in_array($input['status'] ?? '', self::STATUSES, true) ? (string) $input['status'] : '',
            'review' => ($input['review'] ?? '') === '1' ? '1' : '',
            'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 50),
            'from' => $this->date($input['from'] ?? ''),
            'to' => $this->date($input['to'] ?? ''),
        ];
        $wib = new DateTimeZone('Asia/Jakarta');
        $utc = new DateTimeZone('UTC');
        $query = $filters + [
            'fromUtc' => $filters['from'] === '' ? '' : (new DateTimeImmutable($filters['from'], $wib))->setTimezone($utc)->format('Y-m-d H:i:s'),
            'toUtc' => $filters['to'] === '' ? '' : (new DateTimeImmutable($filters['to'], $wib))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
        $page = max(1, (int) ($input['page'] ?? 1));
        $repo = new PaymentOrderRepository();
        $result = $repo->searchForAdmin($query, $page);
        $monthStart = (new DateTimeImmutable('first day of this month 00:00', $wib));

        return [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / 25)),
            'filters' => $filters,
            'summary' => $repo->summary($monthStart->setTimezone($utc)->format('Y-m-d H:i:s'), $monthStart->modify('+1 month')->setTimezone($utc)->format('Y-m-d H:i:s')),
            'attention' => $repo->needingAttention(gmdate('Y-m-d H:i:s')),
        ];
    }

    public function show(string $orderCode): string
    {
        $repo = new PaymentOrderRepository();
        $order = preg_match('/\A[A-Za-z0-9-]{1,50}\z/', $orderCode) === 1 ? $repo->findByCode($orderCode) : null;
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/pages/payments/payment_detail', $this->dashboardShellData() + [
            'order' => $order,
            'events' => $repo->eventsFor((int) $order['id']),
            'username' => $this->currentUsername(),
            'buyer' => (new \App\Repositories\AccountRepository())->find((int) $order['user_id'])['username'] ?? '#' . $order['user_id'],
            'canResolve' => $this->currentRole() === 'Super Admin',
            'reviewer' => $order['reviewed_by'] ? ((new \App\Repositories\AccountRepository())->find((int) $order['reviewed_by'])['username'] ?? '#' . $order['reviewed_by']) : null,
            'feedback' => $this->pendingFeedback(),
        ]);
    }

    public function check(string $orderCode): RedirectResponse
    {
        $repo = new PaymentOrderRepository();
        $order = preg_match('/\A[A-Za-z0-9-]{1,50}\z/', $orderCode) === 1 ? $repo->findByCode($orderCode) : null;
        $back = 'dashboard/payments/' . rawurlencode($orderCode);
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (!service('throttler')->check('admin_pay_check_' . $this->currentUserId(), 20, MINUTE)) {
            return $this->redirectFailure($back, lang('Billing.checkTooOften'));
        }
        try {
            $outcome = (new ApplyPaymentService())->refresh($order, new DateTimeImmutable('now'));
        } catch (Throwable $exception) {
            log_message('warning', 'Admin status check failed for {order}: {message}', ['order' => $orderCode, 'message' => $exception->getMessage()]);
            $this->audit('payment.reconciled', 'failed', 'payment_order', $orderCode, [], 'gateway_error');
            return $this->redirectFailure($back, $this->withReference(lang('Billing.checkFailed')));
        }
        $this->audit('payment.reconciled', 'success', 'payment_order', $orderCode, [], $outcome);

        return $this->redirectSuccess($back, lang('Billing.checkDone'));
    }

    /**
     * POST (Super Admin): resolves an order flagged "needs review" with a reason (PRD AD-01/02).
     */
    public function resolve(string $orderCode): RedirectResponse
    {
        $back = 'dashboard/payments/' . rawurlencode($orderCode);
        if (preg_match('/\A[A-Za-z0-9-]{1,50}\z/', $orderCode) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        $action = (string) $this->request->getPost('action');
        try {
            (new ApplyPaymentService())->resolve($orderCode, $action, (string) $this->request->getPost('note'), $this->currentUserId(), new DateTimeImmutable('now'));
        } catch (\DomainException $exception) {
            $this->audit('payment.resolved', 'denied', 'payment_order', $orderCode, [], mb_substr($action, 0, 16));
            return $this->redirectFailure($back, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Payment resolution failed for {order}: {exception}', ['order' => $orderCode, 'exception' => $exception]);
            $this->audit('payment.resolved', 'failed', 'payment_order', $orderCode, [], 'server_error');
            return $this->redirectFailure($back, $this->withReference(lang('Billing.resolveFailed')));
        }

        return $this->redirectSuccess($back, lang('Billing.resolved_' . $action));
    }

    private function date(mixed $value): string
    {
        $value = is_string($value) ? $value : '';

        return preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : '';
    }
}
