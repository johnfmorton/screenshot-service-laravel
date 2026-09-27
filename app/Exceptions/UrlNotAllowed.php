<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Raised by PublicUrlGuard. Its messages are written for API clients, so a
 * capture that fails with one can report it verbatim.
 */
class UrlNotAllowed extends InvalidArgumentException
{
}
