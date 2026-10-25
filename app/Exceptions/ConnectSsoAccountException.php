<?php

namespace App\Exceptions;

use RuntimeException;

class ConnectSsoAccountException extends RuntimeException
{
    public function __construct(public readonly string $status)
    {
        parent::__construct('Connect SSO account could not be prepared.');
    }
}
