<?php

declare(strict_types=1);

if (! function_exists('flash_feedback')) {
    /**
     * Normalises one-time success/error messages into the shape the feedback partial renders.
     *
     * @return array{type: string, message: string}|null
     */
    function flash_feedback(?string $error, ?string $success): ?array
    {
        $error = trim((string) $error);
        if ($error !== '') {
            return ['type' => 'error', 'message' => $error];
        }

        $success = trim((string) $success);

        return $success !== '' ? ['type' => 'success', 'message' => $success] : null;
    }
}

if (! function_exists('field_error_attrs')) {
    /**
     * aria attributes for an input with a field error (PRD FB-02). Pure: $errors comes from the
     * controller (flash `field_errors`), never from session() here.
     *
     * @param array<string, string> $errors field name => message
     */
    function field_error_attrs(array $errors, string $field, string $inputId, ?string $hintId = null): string
    {
        if (! isset($errors[$field])) {
            return $hintId === null ? '' : ' aria-describedby="' . esc($hintId, 'attr') . '"';
        }
        $describedBy = trim(($hintId ?? '') . ' ' . $inputId . 'Error');

        return ' aria-invalid="true" aria-describedby="' . esc($describedBy, 'attr') . '"';
    }
}
