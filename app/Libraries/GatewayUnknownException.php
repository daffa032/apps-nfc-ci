<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/** The gateway did not answer (timeout, network): the outcome is unknown, not failed. */
final class GatewayUnknownException extends RuntimeException
{
}
