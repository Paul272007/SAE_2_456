<?php

// controllers/User/ProfileEditController.php

declare(strict_types=1);

namespace Controllers\User;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\UserModel;

#[RequirePrivilege(Privilege::USER)]
class ProfileEditController extends Controller
{
    public function get(): void
    {
        require_once 'models/User/UserModel.php';
        $model = new \Models\UserModel();
        $cliNum = (int)$_SESSION['userId'];

        $this->data['user'] = $model->getUserById($cliNum);
        $this->data['csrf_token'] = $_SESSION["csrf_token"];

        $this->render();
    }

    public function post(): void
    {
        verifyCSRFToken();

        require_once 'models/User/UserModel.php';
        $model = new \Models\UserModel();
        $cliNum = (int)$_SESSION['userId'];

        // Info update
        if (isset($_POST['update_info'])) {
            $name = trim($_POST['cli_nom'] ?? '');
            $firstName = trim($_POST['cli_prenom'] ?? '');
            $city = trim($_POST['cli_ville'] ?? '');
            $phone = trim($_POST['cli_telephone'] ?? '');

            if (empty($name) || empty($firstName) || empty($city) || empty($phone)) {
                throw new ClientError(ClientErrorCode::BAD_REQUEST);
            }

            $model->updateUser($cliNum, [
                'cli_nom' => $name,
                'cli_prenom' => $firstName,
                'cli_ville' => $city,
                'cli_telephone' => $phone
            ]);

            $_SESSION['username'] = $name . ' ' . $firstName;
            $_SESSION['flash_success'] = "Profil mis à jour avec succès.";
        }
        
        // Password update
        if (isset($_POST['update_password'])) {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($current) || empty($new) || empty($confirm)) {
                throw new ClientError(ClientErrorCode::BAD_REQUEST);
            }

            if ($new !== $confirm) {
                throw new ClientError(ClientErrorCode::PASSWORD_MISMATCH);
            }

            $user = $model->getUserById($cliNum);
            if (!password_verify($current, $user['cli_mdp'])) {
                throw new ClientError(ClientErrorCode::PASSWORD_ERROR);
            }

            $model->updatePassword($cliNum, password_hash($new, PASSWORD_DEFAULT));
            $_SESSION['flash_success'] = "Mot de passe mis à jour avec succès.";
        }

        redirect('index.php?route=user/dashboard');
    }
}
