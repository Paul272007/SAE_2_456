<?php

// core/ClientError.php

declare(strict_types=1);

namespace Core\Exceptions;

use Exception;

class ClientError extends Exception
{
    private ClientErrorCode $errorCode;
    public function __construct(ClientErrorCode $errorCode)
    {
        parent::__construct();
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): ClientErrorCode
    {
        return $this->errorCode;
    }
}