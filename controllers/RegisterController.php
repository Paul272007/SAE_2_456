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
    protected static array $postFields = ["username", "password", "confirm_password"];
    public function get(): void
    {
        $this->data["csrf_token"] = $_SESSION["csrf_token"];

        $this->render();
    }

    /**
     * @throws ClientError
     * @throws Exception
     */
    public function post(): void
    {
        verifyCSRFToken();
        $this->checkPostFields();

        $username = $_POST["username"];
        $password1 = $_POST["password"];
        $password2 = $_POST["confirm_password"];

        if ($password1 !== $password2)
            throw new ClientError(ClientErrorCode::PASSWORD_MISMATCH);

        if (strlen($password1) < 8 || strlen($password1) > 20)
            throw new ClientError(ClientErrorCode::PASSWORD_LENGTH);

        if (strlen($username) < 1 || strlen($username) > 20)
            throw new ClientError(ClientErrorCode::USERNAME_LENGTH);

        if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $username))
            throw new ClientError(ClientErrorCode::SPECIAL_CHARACTERS);

        $this->model = new RegisterModel();

        if ($this->model->userExists($username))
            throw new ClientError(ClientErrorCode::USER_ALREADY_EXISTS);

        $password = password_hash($password1, PASSWORD_DEFAULT);

        $this->model->register([$username, $password]);
        $_SESSION['flash_success'] = 'account_created';
        redirect('index.php?route=login');
    }
}