<?php

// core/Guest/ErrorController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;

#[RequirePrivilege(Privilege::GUEST)]
class ErrorController extends Controller
{
    // Normally parameters are stored in $_GET but this one is called differently
    public function get($message = null): void
    {
        $this->data['error_message'] = $message;
        $this->render();
    }
}

