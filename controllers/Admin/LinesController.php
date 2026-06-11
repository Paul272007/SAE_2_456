<?php


declare(strict_types=1);

namespace Controllers\Admin;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\LinesModel;

#[RequirePrivilege(Privilege::ADMIN)]
class LinesController extends Controller
{
    public function get(): void
    {
        require_once 'models/LinesModel.php';
        $model = new \Models\LinesModel();
        
        $this->data['lines'] = $model->getLinesWithDetails();
        
        $this->render();
    }
}