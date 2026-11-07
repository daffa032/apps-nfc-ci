<?php

declare(strict_types=1);

namespace App\Services\Cards;

use DomainException;

/**
 * The owner's current plan does not include this feature; callers point them to billing.
 */
final class PlanRequiredException extends DomainException
{
}
