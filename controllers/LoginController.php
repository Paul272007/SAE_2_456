<?php

// controllers/LoginController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use Exception;
use Models\LoginModel;
use Models\ReservationModel;
use Random\RandomException;

#[RequirePrivilege(Privilege::GUEST)]
class LoginController extends Controller
{
    protected static array $postFields = ["email", "password"];

    public function get(): void
    {
                $this->render();
    }

    /**
     * @throws RandomException
     * @throws ClientError
     * @throws Exception
     */
    public function post(): void
    {
                $this->checkPostFields();

        $email    = trim($_POST["email"]);
        $password = $_POST["password"];

        $model = new LoginModel();
        $user  = $model->getUserByEmail($email);

        if (!$user) {
            throw new ClientError(ClientErrorCode::USER_NOT_FOUND);
        }

        if (!password_verify($password, $user["cli_mdp"])) {
            throw new ClientError(ClientErrorCode::PASSWORD_ERROR);
        }

        session_regenerate_id(true);

        $_SESSION["userId"]   = $user["cli_num"];
        $_SESSION["username"] = $user["cli_prenom"] . ' ' . $user["cli_nom"];
        $_SESSION["role"]     = (int)($user["typ_num"] ?? 1);
        $_SESSION["email"]    = $user["cli_courriel"];
        $_SESSION["points"]   = $user["cli_nb_points_ec"];

        // Si un panier était en attente avant la connexion, rediriger vers la confirmation
        if (!empty($_SESSION['cart'])) {
            redirect('index.php?route=reservation/confirm');
        }

        redirect("index.php?route=user/dashboard");
    }
}
