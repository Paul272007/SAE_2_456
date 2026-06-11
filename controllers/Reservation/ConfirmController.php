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
use Models\User\UserModel;

#[RequirePrivilege(Privilege::GUEST)]
class ConfirmController extends Controller
{
    /**
     * Affiche la page de confirmation/paiement avec le récapitulatif du panier.
     */
    public function get(): void
    {
        // Si aucun panier, retour à la liste des lignes
        if (empty($_SESSION['cart'])) {
            redirect('index.php?route=lines');
        }

        $cart = $_SESSION['cart'];
        $totalPrice = 0.0;
        $totalDistance = 0.0;
        $totalPointsEarned = 0;

        foreach ($cart as $item) {
            $totalPrice += $item['prix_total'];
            $totalDistance += $item['distance'];
            $totalPointsEarned += $item['nb_points'];
        }

        $this->data['cart'] = $cart;
        $this->data['total_price'] = $totalPrice;
        $this->data['total_distance'] = $totalDistance;
        $this->data['total_points_earned'] = $totalPointsEarned;
        $this->data['connected']   = isset($_SESSION['userId']);

        if ($this->data['connected']) {
            require_once 'models/User/UserModel.php';
            $userModel = new \Models\UserModel();
            $user = $userModel->getUserById((int)$_SESSION['userId']);
            $pointsAvailable = (int)($user['cli_nb_points_ec'] ?? 0);
            
            $this->data['points_available'] = $pointsAvailable;
            
            // 1. Application de tes paliers de réduction
            $discountPercent = 0;
            if ($pointsAvailable > 1000) {
                $discountPercent = 15;
            } elseif ($pointsAvailable >= 500) {
                $discountPercent = 7;
            } elseif ($pointsAvailable >= 100) {
                $discountPercent = 1;
            }

            // 2. Calcul de la valeur en euros de la remise
            $discountValue = $totalPrice * ($discountPercent / 100.0);
            
            // 3. RESTAURATION DES VARIABLES POUR LA VUE (Évite que la section disparaisse)
            $this->data['points_to_use'] = $pointsAvailable; // La vue retrouve sa variable fétiche !
            $this->data['points_discount_value'] = $discountValue; // Montant de la réduction en €
            $this->data['discount_percent'] = $discountPercent; // Optionnel : si tu veux afficher le "%" dans ton HTML
            $this->data['final_price'] = $totalPrice - $discountValue;
        } else {
            $this->data['points_to_use'] = 0;
            $this->data['points_discount_value'] = 0.0;
            $this->data['discount_percent'] = 0;
            $this->data['final_price'] = $totalPrice;
        }

        $this->render();
    }

    /**
     * Finalise la réservation (POST).
     * @throws ClientError
     * @throws Exception
     */
    public function post(): void
    {
        if (empty($_SESSION['cart'])) {
            redirect('index.php?route=lines');
        }

        $cliNum = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : null;
        $model  = new ReservationModel();
        
        $totalPrice = 0.0;
        $totalPointsEarned = 0;
        foreach ($_SESSION['cart'] as $item) {
            $totalPrice += $item['prix_total'];
            $totalPointsEarned += $item['nb_points'];
        }

        // Calcul sécurisé du palier côté serveur pour l'enregistrement en BDD
        $discountPercent = 0;
        if ($cliNum !== null) {
            require_once 'models/User/UserModel.php';
            $userModel = new \Models\UserModel();
            $user = $userModel->getUserById($cliNum);
            $pointsAvailable = (int)($user['cli_nb_points_ec'] ?? 0);
            
            if ($pointsAvailable > 1000) {
                $discountPercent = 15;
            } elseif ($pointsAvailable >= 500) {
                $discountPercent = 7;
            } elseif ($pointsAvailable >= 100) {
                $discountPercent = 1;
            }
        }

        $cart = $_SESSION['cart'];

        // 1. Vérification de disponibilité
        foreach ($cart as $pending) {
            if (!$model->isAvailable(
                (string)$pending['lig_num'],
                (string)$pending['code_depart'],
                (string)$pending['code_arrivee'],
                (string)$pending['date']
            )) {
                throw new ClientError(ClientErrorCode::BAD_REQUEST);
            }
        }

        // 2. Création des réservations avec le prix réduit
        foreach ($cart as $pending) {
            $itemOriginalPrice = (float)$pending['prix_total'];
            $itemDiscount = $itemOriginalPrice * ($discountPercent / 100.0);
            $itemFinalPrice = $itemOriginalPrice - $itemDiscount;

            $resNum = $model->createReservation(
                $cliNum,
                (int)$pending['tar_num_tranche'],
                (string)$pending['date'],
                (int)$pending['nb_points'],
                $itemFinalPrice 
            );

            // 3. Création de l'étape
            $model->createEtape(
                (string)$pending['lig_num'],
                $resNum,
                $cliNum,
                (string)$pending['code_depart'],
                (string)$pending['code_arrivee'],
                (float)$pending['distance'],
                (string)$pending['date'] . ' 00:00:00'
            );
        }

        // 4. Mise à jour des points (Statut fidélité : on ne déduit aucun point utilisé)
        if ($cliNum !== null) {
            $pointsUsed = 0; 
            if ($totalPointsEarned !== 0) {
                $model->updatePointsAfterReservation($cliNum, $totalPointsEarned, $pointsUsed);
            }
        }

        // 5. Nettoyage de la session
        unset($_SESSION['cart']);

        $_SESSION['flash_success'] = 'Préservation confirmée avec succès !';
        redirect($cliNum !== null ? 'index.php?route=user/dashboard' : 'index.php');
    }
}