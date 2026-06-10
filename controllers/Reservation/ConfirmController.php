<?php

// controllers/Reservation/ConfirmController.php

declare(strict_types=1);

namespace Controllers\Reservation;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use Exception;
use Models\ReservationModel;

#[RequirePrivilege(Privilege::GUEST)]
class ConfirmController extends Controller
{
    /**
     * Affiche la page de confirmation/paiement avec le récapitulatif de la réservation.
     */
    public function get(): void
    {
        // Si aucune réservation en attente, retour à la liste des lignes
        if (empty($_SESSION['pending_reservation'])) {
            redirect('index.php?route=lines');
        }

        $pending = $_SESSION['pending_reservation'];

        $this->data['reservation'] = $pending;
        $this->data['csrf_token']  = $_SESSION['csrf_token'];
        $this->data['connected']   = isset($_SESSION['userId']);

        $this->render();
    }

    /**
     * Finalise la réservation (POST).
     * - Si l'utilisateur est connecté : crée les enregistrements en BDD et met à jour ses points.
     * - Si l'utilisateur est un guest : redirige vers l'inscription avec message flash.
     * @throws ClientError
     * @throws Exception
     */
    public function post(): void
    {
        verifyCSRFToken();

        if (empty($_SESSION['pending_reservation'])) {
            redirect('index.php?route=lines');
        }

        $pending = $_SESSION['pending_reservation'];

        // Vérification des données de session (sécurité minimale)
        $required = ['lig_num', 'code_depart', 'code_arrivee', 'date', 'distance', 'tar_num_tranche', 'prix_total', 'nb_points'];
        foreach ($required as $key) {
            if (!isset($pending[$key])) {
                throw new ClientError(ClientErrorCode::BAD_REQUEST);
            }
        }

        // Si non connecté → sauvegarder et rediriger vers l'inscription
        if (!isset($_SESSION['userId'])) {
            $_SESSION['flash_info'] = 'Créez un compte pour finaliser votre réservation.';
            redirect('index.php?route=register');
        }

        $cliNum = (int)$_SESSION['userId'];
        $model  = new ReservationModel();

        // 1. Vérification de disponibilité au moment de la confirmation (double-check)
        if (!$model->isAvailable(
            (int)$pending['lig_num'],
            (string)$pending['code_depart'],
            (string)$pending['code_arrivee'],
            (string)$pending['date']
        )) {
            unset($_SESSION['pending_reservation']);
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        // 2. Création de la réservation
        $resNum = $model->createReservation(
            $cliNum,
            (int)$pending['tar_num_tranche'],
            (string)$pending['date'],
            (int)$pending['nb_points'],
            (float)$pending['prix_total']
        );

        // 3. Création de l'étape (segment réservé)
        $model->createEtape(
            (int)$pending['lig_num'],
            $resNum,
            (string)$pending['code_depart'],
            (string)$pending['code_arrivee'],
            (float)$pending['distance'],
            (string)$pending['date'] . ' 00:00:00'
        );

        // 4. Mise à jour des points de fidélité du client
        $model->addClientPoints($cliNum, (int)$pending['nb_points']);

        // 5. Nettoyage de la session
        unset($_SESSION['pending_reservation']);

        $_SESSION['flash_success'] = 'reservation_confirmed';
        redirect('index.php?route=user/dashboard');
    }
}
