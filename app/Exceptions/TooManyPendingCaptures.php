<?php

namespace App\Exceptions;

use RuntimeException;

class TooManyPendingCaptures extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("This API key already has {$limit} captures waiting. Wait for some to finish before requesting more.");
    }
}
