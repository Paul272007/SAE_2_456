<?php

// core/ServerErrorCode.php

declare(strict_types=1);

namespace Core\Exceptions;

enum ServerErrorCode: string
{
    case VIEW_NOT_FOUND = 'view does not exist';
    case MODEL_NOT_FOUND = 'model does not exist';
    case CONTROLLER_NOT_FOUND = 'controller does not exist';
    case CONFIG_NOT_FOUND = 'configuration file does not exist';
    case ERROR_404 = '404 page not found';
    case SQL_ERROR = 'sql error';
}