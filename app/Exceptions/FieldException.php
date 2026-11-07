<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * A validation failure tied to one form field (PRD FB-02). The message is shown next to that field
 * and as the summary; the field name must match the input's `name`.
 */
final class FieldException extends DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
