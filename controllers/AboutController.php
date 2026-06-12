<?php

// core/AboutController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;

#[RequirePrivilege(Privilege::GUEST)]
class AboutController extends Controller {}