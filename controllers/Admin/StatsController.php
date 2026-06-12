<?php

// controllers/Admin/StatsController.php

declare(strict_types=1);

namespace Controllers\Admin;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\Admin\AdminModel;

#[RequirePrivilege(Privilege::ADMIN)]
class StatsController extends Controller
{
    public function get(): void
    {
        $model = new AdminModel();
        $this->data['stats'] = $model->getStats();
        $this->render();
    }
}
