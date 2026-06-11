<?php

// controllers/User/DashboardController.php

declare(strict_types=1);

namespace Controllers\User;

use Core\Controller;

use Core\Privilege;
use Core\RequirePrivilege;
use Models\UserModel;

#[RequirePrivilege(Privilege::USER)]
class DashboardController extends Controller
{
    public function get(): void
    {
        require_once 'models/User/UserModel.php';
        $model = new UserModel();
        $cliNum = (int)$_SESSION['userId'];

        $user = $model->getUserById($cliNum);
        $reservations = $model->getUserReservations($cliNum);
        usort($reservations, function($a, $b) {

            return $b['res_num'] <=> $a['res_num']; 
        });

        $this->data['user'] = $user;
        $this->data['reservations'] = $reservations;
        $this->data['username'] = $_SESSION['username'];
        $this->data['isAdmin'] = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 1;

        $this->render();
    }
}