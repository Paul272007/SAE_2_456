<?php

// controllers/Admin/UsereditController.php

declare(strict_types=1);

namespace Controllers\Admin;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\Admin\AdminModel;
use Models\UserModel; // For user details

#[RequirePrivilege(Privilege::ADMIN)]
class UsereditController extends Controller
{
    public function get(): void
    {
        $cliNum = (int)($_GET['id'] ?? 0);
        if ($cliNum <= 0) {
            redirect('index.php?route=admin/users');
        }

        require_once 'models/User/UserModel.php';
        $userModel = new \Models\UserModel();
        
        $this->data['user'] = $userModel->getUserById($cliNum);
        if (!$this->data['user']) {
            redirect('index.php?route=admin/users');
        }
        
        $this->data['reservations'] = $userModel->getUserReservations($cliNum);
        $this->data['csrf_token'] = $_SESSION['csrf_token'];
        
        $this->render();
    }

    public function post(): void
    {
        verifyCSRFToken();
        
        $action = $_POST['action'] ?? '';
        $cliNum = (int)($_POST['cli_num'] ?? 0);
        
        if ($cliNum <= 0) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        if ($action === 'delete') {
            $model = new AdminModel();
            $model->deleteUser($cliNum);
            $_SESSION['flash_success'] = "Utilisateur supprimé.";
            redirect('index.php?route=admin/users');
        } elseif ($action === 'update') {
            require_once 'models/User/UserModel.php';
            $userModel = new \Models\UserModel();
            
            $data = [
                'cli_nom' => $_POST['cli_nom'] ?? '',
                'cli_prenom' => $_POST['cli_prenom'] ?? '',
                'cli_ville' => $_POST['cli_ville'] ?? '',
                'cli_telephone' => $_POST['cli_telephone'] ?? ''
            ];
            
            $userModel->updateUser($cliNum, $data);
            $_SESSION['flash_success'] = "Utilisateur mis à jour.";
            redirect('index.php?route=admin/useredit&id=' . $cliNum);
        }
        
        redirect('index.php?route=admin/users');
    }
}
