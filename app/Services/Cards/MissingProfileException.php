<?php

declare(strict_types=1);

namespace App\Services\Cards;

use DomainException;

/**
 * The account has no card profile yet; callers send the owner to the profile form.
 */
final class MissingProfileException extends DomainException
{
}
