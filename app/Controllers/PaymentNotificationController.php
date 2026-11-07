<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MidtransClient;
use App\Services\Audit\AuditLogService;
use App\Services\Payments\ApplyPaymentService;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use Throwable;

/**
 * POST /payments/midtrans/notification (TECHNICAL "Midtrans" step 4–7). No session; CSRF is
 * exempt for this URI only. Trust comes from the signature, which is checked first, and the order
 * amount on our side; the payload never creates accounts or access by itself.
 *
 * 200 only once the result is stored durably, so Midtrans retries on a database failure. Invalid
 * signatures get 403 and are not stored beyond a short audit event (no payload, no signature),
 * written at most once per IP every 10 minutes so a flood cannot fill the audit table. Requests are
 * rate limited per IP; Midtrans itself sends a few per order, far below the limit.
 */
class PaymentNotificationController extends BaseController
{
    public function midtrans(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        $ipKey = hash('sha256', (string) $this->request->getIPAddress());
        if (! service('throttler')->check('midtrans-ip-' . $ipKey, 120, MINUTE)) {
            return $this->response->setStatusCode(429)->setHeader('Retry-After', '60')->setJSON(['status' => 'too_many']);
        }
        $payload = json_decode((string) $this->request->getBody(), true);
        if (! is_array($payload)) {
            return $this->response->setStatusCode(400)->setJSON(['status' => 'bad_request']);
        }

        if (! (new MidtransClient())->validSignature($payload)) {
            $cache   = cache();
            $seenKey = 'midtrans-rejected-' . $ipKey;
            if ($cache->get($seenKey) === null) {
                $cache->save($seenKey, 1, 600);
                (new AuditLogService())->record('payment.webhook_rejected', 'denied', null, 'payment_order', mb_substr((string) ($payload['order_id'] ?? ''), 0, 32), [], 'bad_signature', 'webhook');
            }

            return $this->response->setStatusCode(403)->setJSON(['status' => 'forbidden']);
        }

        try {
            $outcome = (new ApplyPaymentService())->apply($payload, 'notification', new DateTimeImmutable('now'));
        } catch (Throwable $exception) {
            log_message('error', 'Payment notification failed for {order}: {message}', ['order' => mb_substr((string) ($payload['order_id'] ?? ''), 0, 50), 'message' => $exception->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON(['status' => 'retry']);
        }

        return $this->response->setStatusCode(200)->setJSON(['status' => 'ok', 'outcome' => $outcome]);
    }
}
