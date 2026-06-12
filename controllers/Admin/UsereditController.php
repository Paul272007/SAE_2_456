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
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[RequirePrivilege(Privilege::ADMIN)]
class UsereditController extends Controller
{
    /**
     * @throws SyntaxError
     * @throws ClientError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function get(): void
    {
        $cliNum = (int)($_GET['id'] ?? 0);

        if ($cliNum <= 0) {
            $_SESSION["flash_error"] = "Mauvais numéro de client donné";
            redirect('index.php?route=admin/users');
        }

        $userModel = new UserModel();
        
        $this->data['user'] = $userModel->getUserById($cliNum);

        if (!$this->data['user']) {
            throw new ClientError(ClientErrorCode::USER_NOT_FOUND);
        }
        
        if ($this->data['user']['is_admin'] == 1) {
            throw new ClientError(ClientErrorCode::IMPOSSIBLE_TO_MODIFY_ADMIN);
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
            throw new ClientError(ClientErrorCode::IMPOSSIBLE_TO_MODIFY_ADMIN);
        }

        if ($action === 'delete') {
            $model = new AdminModel();
            $model->deleteUser($cliNum);
            $_SESSION['flash_success'] = "Utilisateur supprimé.";
            redirect('index.php?route=admin/users');
        } elseif ($action === 'make_admin') {
            $model = new AdminModel();
            $model->makeAdmin($cliNum);
            $_SESSION['flash_success'] = "L'utilisateur a été promu administrateur.";
            // Since they are now an admin, we can no longer edit them here
            redirect('index.php?route=admin/users');
        } elseif ($action === 'update') {
            $phone = $_POST['cli_telephone'] ?? '';
            $cleanPhone = str_replace([' ', '.', '-'], '', $phone);
            
            if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $cleanPhone) && !preg_match('/^\+[0-9]{1,3}[0-9]{9,15}$/', $cleanPhone)) {
                throw new ClientError(ClientErrorCode::INVALID_PHONE);
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
