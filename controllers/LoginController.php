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

        if (isset($user['is_deleted']) && (int)$user['is_deleted'] === 1) {
            throw new ClientError(ClientErrorCode::ACCOUNT_DELETED);
        }

        if (!password_verify($password, $user["cli_mdp"])) {
            throw new ClientError(ClientErrorCode::PASSWORD_ERROR);
        }

        session_regenerate_id(true);

        // Check if user has not connected for a year
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        if (!empty($user['cli_date_connec']) && $user['cli_date_connec'] < $oneYearAgo) {
            $model->resetCurrentPoints((int)$user['cli_num']);
            $user["cli_nb_points_ec"] = 0; // update local variable for session
            $_SESSION['flash_info'] = "Vos points de fidélité ont été réinitialisés car vous ne vous étiez pas connecté depuis plus d'un an.";
        }

        // Check for grade upgrade
        $types = $model->getClientTypes();
        $currentTypeNum = (int)$user['typ_num'];
        $pointsTot = (int)($user['cli_nb_points_tot'] ?? 0);
        
        // Trouver la limite du grade actuel
        $currentLimit = -1;
        foreach ($types as $t) {
            if ((int)$t['typ_num'] === $currentTypeNum) {
                $currentLimit = (int)$t['typ_pt_limite'];
                break;
            }
        }

        $newType = $currentTypeNum;
        $newTypeName = '';

        foreach ($types as $type) {
            if ($pointsTot >= (int)$type['typ_pt_limite']) {
                if ((int)$type['typ_num'] !== $currentTypeNum && (int)$type['typ_pt_limite'] > $currentLimit) {
                    $newType = (int)$type['typ_num'];
                    $newTypeName = $type['typ_nom'];
                }
                break; // Because it's DESC, the first match >= is the max grade they qualify for based on points
            }
        }

        if ($newType !== $currentTypeNum && $newTypeName !== '') {
            $model->updateUserType((int)$user['cli_num'], $newType);
            $_SESSION['flash_success'] = "Félicitations ! Vous avez atteint le grade supérieur : $newTypeName.";
            $user['typ_num'] = $newType;
        }

        $_SESSION["userId"]   = $user["cli_num"];
        $_SESSION["username"] = $user["cli_prenom"] . ' ' . $user["cli_nom"];
        $_SESSION["role"]     = (int)($user["typ_num"] ?? 1);
        $_SESSION["is_admin"] = (int)($user["is_admin"] ?? 0);
        $_SESSION["email"]    = $user["cli_courriel"];
        $_SESSION["points"]   = $user["cli_nb_points_ec"];

        // Mettre à jour la date de dernière connexion
        $model->updateLastConnection((int)$user["cli_num"]);

        // Si un panier était en attente avant la connexion, rediriger vers la confirmation
        if (!empty($_SESSION['cart'])) {
            redirect('index.php?route=reservation/confirm');
        }

        redirect("index.php?route=user/dashboard");
    }
}
