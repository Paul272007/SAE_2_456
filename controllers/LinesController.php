<?php

// controllers/LinesController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\LinesModel;

#[RequirePrivilege(Privilege::GUEST)]
class LinesController extends Controller
{
    public function get(): void
    {
        $this->model = new LinesModel();
        $this->data["lines"] = $this->model->getLines();
        $this->render();
    }
}