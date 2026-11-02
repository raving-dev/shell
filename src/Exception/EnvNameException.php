<?php

namespace RavingDev\Shell\Exception;

use RavingDev\Shell\Exception\BeforeExecuteException;
use Throwable;

class EnvNameException extends BeforeExecuteException
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
