<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\FieldException;
use App\Services\Audit\AuditLogService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * One shape for the result of a mutation, read back by partials/feedback/flash.php.
 */
trait RespondsWithFeedback
{
    /**
     * Records an audit event for the current actor (PRD AUD-01). Never throws.
     *
     * @param list<string> $changedFields field names only
     */
    protected function audit(string $action, string $outcome, ?string $targetType = null, int|string|null $targetId = null, array $changedFields = [], ?string $reason = null): void
    {
        (new AuditLogService())->record($action, $outcome, (int) session()->get('id_user') ?: null, $targetType, $targetId, $changedFields, $reason);
    }

    /**
     * Runs a high-risk mutation so that it is kept only if its success audit event is written too
     * (PRD AUD-02, ARC-04). Exceptions, including a failed audit write, reach the caller unchanged.
     *
     * @param callable(): mixed $mutation
     * @param list<string> $changedFields field names only
     */
    protected function auditedMutation(string $action, ?string $targetType, int|string|null $targetId, callable $mutation, array $changedFields = [], ?string $reason = null): mixed
    {
        return (new AuditLogService())->withRequiredAudit(
            (int) session()->get('id_user') ?: null,
            $mutation,
            static fn (): array => [$action, $targetType, $targetId, $changedFields, $reason]
        );
    }

    /**
     * Server-side failure message with the request ID support can look up in Activity Logs.
     */
    protected function withReference(string $message): string
    {
        return $message . ' (Ref: ' . AuditLogService::requestId() . ')';
    }

    protected function redirectSuccess(string $to, string $message): RedirectResponse
    {
        return redirect()->to($to)->with('success', $message);
    }

    protected function redirectFailure(string $to, string $message): RedirectResponse
    {
        return redirect()->to($to)->withInput()->with('error', $message);
    }

    /**
     * Failure tied to one field (PRD FB-02): the summary toast plus the message next to the field.
     * Password fields are never kept in old input.
     */
    protected function redirectFieldFailure(string $to, FieldException $exception, array $extraFlash = []): RedirectResponse
    {
        session()->setFlashdata(['field_errors' => [$exception->field => $exception->getMessage()]] + $extraFlash);

        return $this->redirectFailureWithoutSecrets($to, $exception->getMessage());
    }

    /**
     * Field errors left by the previous request, for the form partials.
     *
     * @return array<string, string>
     */
    protected function pendingFieldErrors(): array
    {
        $errors = session()->getFlashdata('field_errors');

        return is_array($errors) ? array_map('strval', $errors) : [];
    }

    protected function backWithFailure(string $message): RedirectResponse
    {
        return redirect()->back()->withInput()->with('error', $message);
    }

    /**
     * Like redirectFailure(), but never keeps password fields in the session's old input.
     */
    protected function redirectFailureWithoutSecrets(string $to, string $message): RedirectResponse
    {
        $input = (array) $this->request->getPost();
        unset($input['password'], $input['repeat_password'], $input['current_password'], $input[csrf_token()]);
        session()->setFlashdata('_ci_old_input', ['get' => [], 'post' => $input]);

        return redirect()->to($to)->with('error', $message);
    }

    /**
     * Flash message left by the previous request, for the dashboard layout.
     *
     * @return array{type: string, message: string}|null
     */
    protected function pendingFeedback(): ?array
    {
        $session = session();

        return flash_feedback(
            is_string($error = $session->getFlashdata('error')) ? $error : null,
            is_string($success = $session->getFlashdata('success')) ? $success : null
        );
    }
}
