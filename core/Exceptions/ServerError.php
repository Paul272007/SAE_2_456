<?php

// core/ServerError.php

declare(strict_types=1);

namespace Core\Exceptions;

use Exception;

class ServerError extends Exception
{
    public function __construct(ServerErrorCode $errorCode, string $culprit)
    {
        parent::__construct($errorCode->value . " : " . $culprit . ".");
    }
}