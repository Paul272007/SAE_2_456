<?php

// core/LoginController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Exceptions\ServerError;
use Core\Privilege;
use Core\RequirePrivilege;
use Exception;
use Random\RandomException;

#[RequirePrivilege(Privilege::GUEST)]
class LoginController extends Controller
{
    protected static array $postFields = ["username", "password"];
    public function get(): void
    {
        $this->data["csrf_token"] = $_SESSION["csrf_token"];
        $this->render();
    }

    /**
     * @throws RandomException
     * @throws ClientError
     * @throws ServerError
     * @throws Exception
     */
    public function post(): void
    {
        verifyCSRFToken();

        $this->checkPostFields();

        $username = $_POST["username"];
        $password = $_POST["password"];

        $this->model = $this->getModel();
        $user = $this->model->getUserByUsername($username);

        if (!$user) {
            throw new ClientError(ClientErrorCode::USER_NOT_FOUND);
        }

        if (password_verify($password, $user["password"])) {
            session_regenerate_id(true);
            $_SESSION["userId"] = $user["user_id"];
            $_SESSION["username"] = $user["user_name"];
            $_SESSION["role"] = $user["user_role"];
            $_SESSION["theme"] = $user["user_theme"];

            buildCSRFToken();

            redirect("index.php?route=user/dashboard");
        } else {
            throw new ClientError(ClientErrorCode::PASSWORD_ERROR);
        }
    }
}
