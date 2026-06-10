<?php

// controllers/User/DashboardController.php

declare(strict_types=1);

namespace Controllers\User;

use Core\Controller;

use Core\Privilege;
use Core\RequirePrivilege;

#[RequirePrivilege(Privilege::USER)]
class DashboardController extends Controller
{
    public function get(): void
    {
        require_once 'models/User/UserModel.php';
        $model = new \Models\UserModel();
        $cliNum = (int)$_SESSION['userId'];

        $user = $model->getUserById($cliNum);
        $reservations = $model->getUserReservations($cliNum);

        $this->data['user'] = $user;
        $this->data['reservations'] = $reservations;
        $this->data['username'] = $_SESSION['username'];
        $this->data['csrf_token'] = $_SESSION["csrf_token"];

        $this->render();
    }
}