<?php

// controllers/Admin/UsersController.php

declare(strict_types=1);

namespace Controllers\Admin;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\Admin\AdminModel;

#[RequirePrivilege(Privilege::ADMIN)]
class UsersController extends Controller
{
    public function get(): void
    {
        $model = new AdminModel();
        
        $onlyInactive = isset($_GET['filter']) && $_GET['filter'] === 'inactive';
        
        $this->data['users'] = $model->getUsers($onlyInactive);
        $this->data['filter'] = $onlyInactive ? 'inactive' : 'all';
        
        $this->render();
    }
}
