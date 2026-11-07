<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Repositories\AuditLogRepository;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Records accepted and denied server actions (PRD AUD-01). Callers decide authorization first;
 * this only writes. Action codes are a fixed list so filters and labels stay meaningful.
 *
 * Writing an audit event must never turn a failed action into a success. For ordinary actions a
 * failing audit write must not break the action either: it is logged as an operational error with
 * the request ID for reconciliation (TECHNICAL). High-risk actions use withRequiredAudit(), where a
 * failed audit write cancels the action.
 */
final class AuditLogService
{
    /** Allowed action codes; the page shows translated labels from app/Language/{id,en}/Audit.php. */
    public const ACTIONS = [
        'auth.login' => 'Masuk',
        'auth.login_failed' => 'Gagal masuk',
        'auth.access_denied' => 'Akses ditolak',
        'profile.updated' => 'Profil diubah',
        'gallery.uploaded' => 'Foto galeri diunggah',
        'gallery.deleted' => 'Foto galeri dihapus',
        'gallery.caption_updated' => 'Keterangan foto diubah',
        'gallery.layout_updated' => 'Tata letak galeri diubah',
        'social.updated' => 'Tautan sosial diubah',
        'brand.updated' => 'Aset merek diubah',
        'payment.checkout' => 'Tagihan dibuat',
        'payment.applied' => 'Pembayaran diterapkan',
        'payment.webhook_rejected' => 'Notifikasi pembayaran ditolak',
        'payment.reconciled' => 'Status pembayaran diperiksa',
        'payment.cancelled' => 'Tagihan dibatalkan',
        'payment.resolved' => 'Tinjauan pembayaran diselesaikan',
        'payment.refunded' => 'Pembayaran dikembalikan, masa aktif dicabut',
        'theme.selected' => 'Tampilan kartu dipilih',
        'account.settings_updated' => 'Pengaturan akun diubah',
        'user.created' => 'User dibuat',
        'user.updated' => 'User diubah',
        'user.deleted' => 'User dihapus',
        'admin.created' => 'Admin dibuat',
        'admin.updated' => 'Admin diubah',
        'admin.deleted' => 'Admin dihapus',
        'account.suspended' => 'Akun ditangguhkan',
        'account.restored' => 'Akun dipulihkan',
        'subscription.granted' => 'Masa aktif diberikan',
        'subscription.revoked' => 'Masa aktif dicabut',
        'plan.updated' => 'Paket diubah',
        'template.created' => 'Template dibuat',
        'template.status_changed' => 'Status template diubah',
        'template.renamed' => 'Nama template diubah',
        'template.limits_changed' => 'Batas slot template diubah',
        'audit.viewed' => 'Activity Logs dibuka',
    ];

    public const OUTCOMES = ['success' => 'Berhasil', 'denied' => 'Ditolak', 'failed' => 'Gagal'];

    private static ?string $requestId = null;

    public function __construct(private ?AuditLogRepository $events = null, private ?BaseConnection $db = null)
    {
        $this->events ??= new AuditLogRepository();
    }

    /**
     * High-risk mutation (PRD AUD-02, ARC-04): runs $mutation and writes its success event in one
     * transaction, so the change is kept only if the audit row is too. Repository transactions
     * inside $mutation nest into this one (CI4 commits only the outermost). Any exception, including
     * a failed audit write, rolls everything back and is rethrown for the caller's failure message.
     *
     * @template T
     * @param callable(): T $mutation
     * @param callable(T): array{0: string, 1: ?string, 2: int|string|null, 3?: list<string>, 4?: ?string} $event
     *        [action, targetType, targetId, changedFields, reason] built from the mutation result
     * @return T
     */
    public function withRequiredAudit(?int $actorId, callable $mutation, callable $event): mixed
    {
        $this->db ??= db_connect();
        $this->db->transBegin();
        try {
            $result = $mutation();
            [$action, $targetType, $targetId] = $args = $event($result);
            $this->events->append($this->event($action, 'success', $actorId, $targetType, $targetId, $args[3] ?? [], $args[4] ?? null));
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Audit transaction failed.');
            }
            $this->db->transCommit();

            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /**
     * One ID per HTTP request, safe to show in a failure toast for support lookups.
     */
    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(8));
    }

    /**
     * @param list<string> $changedFields field names only, never values
     */
    public function record(
        string $action,
        string $outcome,
        ?int $actorId,
        ?string $targetType = null,
        int|string|null $targetId = null,
        array $changedFields = [],
        ?string $reason = null,
        string $actorType = 'user'
    ): void {
        if (!isset(self::ACTIONS[$action]) || !isset(self::OUTCOMES[$outcome])) {
            log_message('error', 'Unknown audit action/outcome: ' . $action . '/' . $outcome);
            return;
        }
        $event = $this->event($action, $outcome, $actorId, $targetType, $targetId, $changedFields, $reason, $actorType);

        try {
            $this->events->append($event);
        } catch (Throwable $exception) {
            log_message('error', 'Audit write failed [{request}] {action}: {message}', [
                'request' => $event['request_id'],
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Appends one event inside the caller's open transaction and throws when it cannot be written,
     * so the caller's mutation is rolled back with it (payment application, ARC-04).
     *
     * @param list<string> $changedFields field names only, never values
     */
    public function appendRequired(
        string $action,
        string $outcome,
        ?int $actorId,
        ?string $targetType,
        int|string|null $targetId,
        array $changedFields = [],
        ?string $reason = null,
        string $actorType = 'user'
    ): void {
        $db = $this->db ?? db_connect();
        if (!$db->table('audit_events')->insert($this->event($action, $outcome, $actorId, $targetType, $targetId, $changedFields, $reason, $actorType))) {
            throw new RuntimeException('Audit write failed.');
        }
    }

    /**
     * @param list<string> $changedFields
     */
    private function event(
        string $action,
        string $outcome,
        ?int $actorId,
        ?string $targetType,
        int|string|null $targetId,
        array $changedFields,
        ?string $reason,
        string $actorType = 'user'
    ): array {
        if (!isset(self::ACTIONS[$action]) || !isset(self::OUTCOMES[$outcome])) {
            throw new DomainException('Unknown audit action/outcome: ' . $action . '/' . $outcome);
        }

        return [
            'request_id' => self::requestId(),
            'actor_id' => $actorId !== null && $actorId > 0 ? $actorId : null,
            'actor_type' => $actorType,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId === null ? null : substr((string) $targetId, 0, 32),
            'outcome' => $outcome,
            'reason' => $reason === null ? null : mb_substr($reason, 0, 64),
            'changed_fields' => $changedFields === [] ? null : mb_substr(implode(',', array_unique($changedFields)), 0, 255),
            'occurred_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{rows: list<array>, total: int, pages: int, page: int, filters: array<string, string>}
     */
    public function search(array $input, int $page): array
    {
        $filters = [
            'action' => isset(self::ACTIONS[$input['action'] ?? '']) ? (string) $input['action'] : '',
            'outcome' => isset(self::OUTCOMES[$input['outcome'] ?? '']) ? (string) $input['outcome'] : '',
            'actor' => mb_substr(trim((string) ($input['actor'] ?? '')), 0, 64),
            'target' => preg_match('/\A[A-Za-z0-9_-]{1,32}\z/', (string) ($input['target'] ?? '')) ? (string) $input['target'] : '',
            'from' => $this->wibDateToUtc((string) ($input['from'] ?? ''), false),
            'to' => $this->wibDateToUtc((string) ($input['to'] ?? ''), true),
        ];
        $page = max(1, $page);
        $result = $this->events->search($filters, $page);

        return $result + [
            'pages' => max(1, (int) ceil($result['total'] / AuditLogRepository::PAGE_SIZE)),
            'page' => $page,
            'filters' => [
                'action' => $filters['action'],
                'outcome' => $filters['outcome'],
                'actor' => $filters['actor'],
                'target' => $filters['target'],
                'from' => $filters['from'] === '' ? '' : (string) $input['from'],
                'to' => $filters['to'] === '' ? '' : (string) $input['to'],
            ],
        ];
    }

    /**
     * A WIB calendar day (Y-m-d) as a UTC bound: the start of the day, or the start of the next day.
     */
    private function wibDateToUtc(string $date, bool $endOfDay): string
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Jakarta'));
        if ($day === false || $day->format('Y-m-d') !== $date) {
            return '';
        }

        return ($endOfDay ? $day->modify('+1 day') : $day)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
