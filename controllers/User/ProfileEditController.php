<?php

// controllers/User/ProfileEditController.php

declare(strict_types=1);

namespace Controllers\User;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\User\UserModel;

#[RequirePrivilege(Privilege::USER)]
class ProfileEditController extends Controller
{
    public function get(): void
    {
        $model = new UserModel();
        $cliNum = (int)$_SESSION['userId'];

        $this->data['user'] = $model->getUserById($cliNum);

        $this->render();
    }

    public function post(): void
    {
        
        $model = new UserModel();
        $cliNum = (int)$_SESSION['userId'];

        // Info update
        if (isset($_POST['update_info'])) {
            $name = trim($_POST['cli_nom'] ?? '');
            $firstName = trim($_POST['cli_prenom'] ?? '');
            $city = trim($_POST['cli_ville'] ?? '');
            $phone = trim($_POST['cli_telephone'] ?? '');

            if (empty($name) || empty($firstName) || empty($city) || empty($phone)) {
                throw new ClientError(ClientErrorCode::EMPTY_FIELD);
            }

            $cleanPhone = str_replace([' ', '.', '-'], '', $phone);
            if (!preg_match('/^[0-9]{10}$/', $cleanPhone) && !preg_match('/^\+[0-9]{1,3}[0-9]{9,15}$/', $cleanPhone)) {
                throw new ClientError(ClientErrorCode::INVALID_PHONE);
            }

            $model->updateUser($cliNum, [
                'cli_nom' => $name,
                'cli_prenom' => $firstName,
                'cli_ville' => $city,
                'cli_telephone' => $cleanPhone
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

        // Account deletion
        if (isset($_POST['delete_account'])) {
            $model->deleteUser($cliNum);
            session_destroy();
            redirect('index.php?route=home');
        }

        redirect('index.php?route=user/dashboard');
    }
}
