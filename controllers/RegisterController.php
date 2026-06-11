<?php

// controllers/Guest/RegisterController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use Exception;
use Models\RegisterModel;

#[RequirePrivilege(Privilege::GUEST)]
class RegisterController extends Controller
{
    protected static array $postFields = ["cli_nom", "cli_prenom", "cli_ville", "cli_telephone", "cli_courriel", "password", "confirm_password"];
    public function get(): void
    {
        if ($_SESSION["role"] > 0) {
            throw new ClientError(ClientErrorCode::BAD_PLACE);
        }
        $this->render();
    }

    /**
     * @throws ClientError
     * @throws Exception
     */
    public function post(): void
    {
                $this->checkPostFields();

        $name = trim($_POST["cli_nom"]);
        $firstName = trim($_POST["cli_prenom"]);
        $city = trim($_POST["cli_ville"]);
        $phoneNumber = trim($_POST["cli_telephone"]);
        $email = trim($_POST["cli_courriel"]);
        $password1 = $_POST["password"];
        $password2 = $_POST["confirm_password"];

        if ($password1 !== $password2)
            throw new ClientError(ClientErrorCode::PASSWORD_MISMATCH);

        if (strlen($password1) < 8 || strlen($password1) > 20)
            throw new ClientError(ClientErrorCode::PASSWORD_LENGTH);

        if (strlen($name) < 1 || strlen($name) > 20 || strlen($firstName) < 1 || strlen($firstName) > 20)
            throw new ClientError(ClientErrorCode::NAME_LENGTH);

        if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $name) || !preg_match('/^[a-zA-Z0-9\s\-]+$/', $firstName) || !preg_match('/^[a-zA-Z0-9\s\-]+$/', $city))
            throw new ClientError(ClientErrorCode::SPECIAL_CHARACTERS);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            throw new ClientError(ClientErrorCode::BAD_REQUEST);

        // Clean phone number before validation
        $cleanPhone = str_replace([' ', '.', '-'], '', $phoneNumber);

        if (!preg_match('/^[0-9]{10}$/', $cleanPhone) && !preg_match('/^\+[0-9]{1,3}[0-9]{9,15}$/', $cleanPhone))
            throw new ClientError(ClientErrorCode::BAD_REQUEST);

        $this->model = new RegisterModel();

        if ($this->model->userExists($email))
            throw new ClientError(ClientErrorCode::USER_ALREADY_EXISTS);

        $password = password_hash($password1, PASSWORD_DEFAULT);

        $defaultDep = $this->model->getDefaultDepartment();

        $this->model->register([
            1,              // typ_num
            $defaultDep,    // dep_num
            $name,          // cli_nom
            $firstName,     // cli_prenom
            $city,          // cli_ville
            $cleanPhone,    // cli_telephone
            $email,         // cli_courriel
            $password,      // cli_password
            date('Y-m-d')   // cli_date_connec
        ]);
        $_SESSION['flash_success'] = 'Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.';

        // Si un panier était en attente, connecter directement et finaliser
        if (!empty($_SESSION['cart'])) {
            session_regenerate_id(true);
            $_SESSION['userId']   = $this->model->getLastClientId();
            $_SESSION['username'] = $name . ' ' . $firstName;
            $_SESSION['role']     = 1;
            $_SESSION['email']    = $email;
            $_SESSION['points']   = 0;
                                    redirect('index.php?route=reservation/confirm');
        }

        redirect('index.php?route=login');
    }
}