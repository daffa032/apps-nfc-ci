<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\FieldException;
use App\Repositories\PaymentOrderRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Cards\DashboardService;
use App\Services\Payments\ApplyPaymentService;
use App\Services\Payments\CancelPaymentService;
use App\Services\Payments\CheckoutService;
use App\Traits\ResolvesCurrentUser;
use App\Traits\RespondsWithFeedback;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;
use DomainException;
use Throwable;

/**
 * "Paket & pembayaran" (PRD BI-01..03, EX-01). Owners renew through Midtrans Snap; the page only
 * shows a payment as successful once the server has verified it (notification or status check),
 * never because the browser came back from the gateway. Manual renewal via an admin stays available.
 */
class BillingController extends BaseController
{
    use ResolvesCurrentUser;
    use RespondsWithFeedback;

    private const PAGE = 'dashboard/billing';

    protected $helpers = ['asset', 'feedback', 'date'];

    public function index(): string|RedirectResponse
    {
        $summary = (new DashboardService())->summaryFor($this->currentUserId(), new DateTimeImmutable('now'));
        if ($summary === null) {
            return redirect()->to('/login');
        }

        $config = config('Billing');
        $whatsapp = preg_replace('/\D+/', '', $config->whatsapp) ?? '';
        $email = filter_var($config->email, FILTER_VALIDATE_EMAIL) ? $config->email : '';
        $message = lang('Billing.message', [$this->currentUsername()]);
        $orders = new PaymentOrderRepository();

        // Back from the gateway (?order=...): show what the server knows; never trust the URL.
        $returned = null;
        $code = (string) $this->request->getGet('order');
        $notYours = false;
        if ($code !== '' && preg_match('/\A[A-Za-z0-9-]{1,50}\z/', $code) === 1) {
            $returned = $orders->findOwnedOrder($code, $this->currentUserId());
            // A link for an invoice of another account (e.g. an email opened while signed in as
            // someone else): say so plainly, without revealing whether that invoice exists.
            $notYours = $returned === null;
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/pages/billing/billing', $this->dashboardShellData() + $summary + [
            'whatsappUrl' => $whatsapp !== '' ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($message) : null,
            'emailUrl' => $email !== '' ? 'mailto:' . $email . '?subject=' . rawurlencode(lang('Billing.subject')) . '&body=' . rawurlencode($message) : null,
            'transferLines' => array_values(array_filter(array_map('trim', preg_split('/\R/', $config->transferInstructions) ?: []), 'strlen')),
            'offers' => $this->currentRole() === 'User' ? (new CheckoutService())->offers() : [],
            'gatewayOn' => config('Midtrans')->isConfigured(),
            // History shows paid invoices only; open ones get their own box with "continue paying".
            'orders' => $orders->paidForUser($this->currentUserId(), 10),
            'waitingOrders' => $orders->waitingForUser($this->currentUserId(), gmdate('Y-m-d H:i:s')),
            'returnedOrder' => $returned,
            'orderNotYours' => $notYours,
            'requestKey' => bin2hex(random_bytes(16)),
            'feedback' => $this->pendingFeedback(),
        ]);
    }

    /**
     * POST: creates (or reuses) the owner's order and sends them to the Midtrans payment page.
     */
    public function checkout(): RedirectResponse
    {
        try {
            $result = (new CheckoutService())->checkout(
                $this->currentUserId(),
                (string) $this->request->getPost('plan_code'),
                (string) $this->request->getPost('request_key'),
                new DateTimeImmutable('now')
            );
        } catch (FieldException|DomainException $exception) {
            $this->audit('payment.checkout', 'denied', 'account', $this->currentUserId(), [], 'checkout_refused');
            return $this->redirectFailure(self::PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Checkout failed: {exception}', ['exception' => $exception]);
            return $this->redirectFailure(self::PAGE, $this->withReference(lang('Billing.checkoutFailed')));
        }

        $this->audit('payment.checkout', 'success', 'payment_order', $result['order_code']);
        if ($result['redirect_url'] !== null && in_array($result['status'], ['pending', 'creating'], true)) {
            // Only Midtrans hosts are ever redirected to.
            $host = (string) parse_url($result['redirect_url'], PHP_URL_HOST);
            if (in_array($host, ['app.midtrans.com', 'app.sandbox.midtrans.com'], true)) {
                return redirect()->to($result['redirect_url']);
            }
        }

        return redirect()->to(self::PAGE . '?order=' . rawurlencode($result['order_code']));
    }

    /**
     * POST: cancels one of the owner's unpaid invoices (e.g. to pick another payment method).
     */
    public function cancel(string $orderCode): RedirectResponse
    {
        if (!service('throttler')->check('pay_cancel_' . $this->currentUserId(), 6, MINUTE)) {
            return $this->redirectFailure(self::PAGE, lang('Billing.checkTooOften'));
        }
        try {
            $result = (new CancelPaymentService())->cancel($this->currentUserId(), $orderCode, new DateTimeImmutable('now'));
        } catch (DomainException $exception) {
            return $this->redirectFailure(self::PAGE, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('warning', 'Cancel failed for {order}: {message}', ['order' => $orderCode, 'message' => $exception->getMessage()]);
            return $this->redirectFailure(self::PAGE, $this->withReference(lang('Billing.cancelFailed')));
        }
        $this->audit('payment.cancelled', 'success', 'payment_order', $orderCode, [], $result);

        return $result === CancelPaymentService::ALREADY_PAID
            ? $this->redirectSuccess(self::PAGE . '?order=' . rawurlencode($orderCode), lang('Billing.cancelAlreadyPaid'))
            : $this->redirectSuccess(self::PAGE, lang('Billing.cancelDone'));
    }

    /**
     * GET: printable receipt of one of the owner's paid orders. A refunded order keeps its receipt
     * (the payment happened) with a "refunded" stamp, so the refund email's link still works.
     * Another owner's order is a 404, never a hint that it exists.
     */
    public function receipt(string $orderCode): string
    {
        $order = preg_match('/\A[A-Za-z0-9-]{1,50}\z/', $orderCode) === 1
            ? (new PaymentOrderRepository())->findOwnedOrder($orderCode, $this->currentUserId())
            : null;
        if ($order === null || !in_array($order['status'], ['paid', 'refunded'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('dashboard/pages/billing/receipt', $this->dashboardShellData() + [
            'order' => $order,
            'period' => $order['subscription_id'] ? (new SubscriptionRepository())->find((int) $order['subscription_id']) : null,
            'username' => $this->currentUsername(),
        ]);
    }

    /**
     * POST: asks Midtrans for the current status of one of the owner's orders.
     */
    public function check(string $orderCode): RedirectResponse
    {
        $order = (new PaymentOrderRepository())->findOwnedOrder($orderCode, $this->currentUserId());
        if ($order === null) {
            return $this->redirectFailure(self::PAGE, lang('Billing.orderMissing'));
        }
        if (!service('throttler')->check('pay_check_' . $this->currentUserId(), 6, MINUTE)) {
            return $this->redirectFailure(self::PAGE . '?order=' . rawurlencode($orderCode), lang('Billing.checkTooOften'));
        }
        try {
            (new ApplyPaymentService())->refresh($order, new DateTimeImmutable('now'));
        } catch (Throwable $exception) {
            log_message('warning', 'Status check failed for {order}: {message}', ['order' => $orderCode, 'message' => $exception->getMessage()]);
            return $this->redirectFailure(self::PAGE . '?order=' . rawurlencode($orderCode), $this->withReference(lang('Billing.checkFailed')));
        }

        return redirect()->to(self::PAGE . '?order=' . rawurlencode($orderCode));
    }
}
