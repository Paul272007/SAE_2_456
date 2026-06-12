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
use Models\User\UserModel;

#[RequirePrivilege(Privilege::ADMIN)]
class UsereditController extends Controller
{
    public function get(): void
    {
        $cliNum = (int)($_GET['id'] ?? 0);
        if ($cliNum <= 0) {
            redirect('index.php?route=admin/users');
        }

        $userModel = new UserModel();
        
        $this->data['user'] = $userModel->getUserById($cliNum);
        if (!$this->data['user']) {
            redirect('index.php?route=admin/users');
        }
        
        if ($this->data['user']['is_admin'] == 1) {
            $_SESSION['flash_error'] = "Impossible de modifier le profil d'un administrateur.";
            redirect('index.php?route=admin/users');
        }
        
        $this->data['reservations'] = $userModel->getUserReservations($cliNum);
        
        $this->render();
    }

    public function post(): void
    {
                
        $action = $_POST['action'] ?? '';
        $cliNum = (int)($_POST['cli_num'] ?? 0);
        
        if ($cliNum <= 0) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        $userModel = new UserModel();
        $targetUser = $userModel->getUserById($cliNum);
        
        if ($targetUser && $targetUser['is_admin'] == 1) {
            $_SESSION['flash_error'] = "Opération interdite sur un compte administrateur.";
            redirect('index.php?route=admin/users');
            return;
        }

        if ($action === 'delete') {
            $model = new AdminModel();
            $model->deleteUser($cliNum);
            $_SESSION['flash_success'] = "Utilisateur supprimé.";
            redirect('index.php?route=admin/users');
        } elseif ($action === 'update') {
            $phone = $_POST['cli_telephone'] ?? '';
            $cleanPhone = str_replace([' ', '.', '-'], '', $phone);
            
            if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $cleanPhone) && !preg_match('/^\+[0-9]{1,3}[0-9]{9,15}$/', $cleanPhone)) {
                // Here we might just use flash error because there's no ClientError handling here natively
                $_SESSION['flash_error'] = "Format de numéro de téléphone invalide.";
                redirect('index.php?route=admin/useredit&id=' . $cliNum);
                return;
            }

            $data = [
                'cli_nom' => $_POST['cli_nom'] ?? '',
                'cli_prenom' => $_POST['cli_prenom'] ?? '',
                'cli_ville' => $_POST['cli_ville'] ?? '',
                'cli_telephone' => $cleanPhone
            ];
            
            $userModel->updateUser($cliNum, $data);
            $_SESSION['flash_success'] = "Utilisateur mis à jour.";
            redirect('index.php?route=admin/useredit&id=' . $cliNum);
        }
        
        redirect('index.php?route=admin/users');
    }
}
