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
            $totalPrice += (float)$item['prix_total'];
            $totalDistance += (float)$item['distance'];
            $totalPointsEarned += (int)$item['nb_points'];
        }

        $this->data['cart'] = $cart;
        $this->data['total_price'] = $totalPrice;
        $this->data['total_distance'] = $totalDistance;
        $this->data['total_points_earned'] = $totalPointsEarned;
        $this->data['connected']   = isset($_SESSION['userId']);

        if ($this->data['connected']) {
            $userModel = new \Models\UserModel();
            $user = $userModel->getUserById((int)$_SESSION['userId']);
            
            // Sécurité Oracle (Supporte les clés retournées en MAJUSCULES ou minuscules)
            $pointsAvailable = (int)($user['CLI_NB_POINTS_EC'] ?? $user['cli_nb_points_ec'] ?? 0);
            $this->data['points_available'] = $pointsAvailable;
            
            // --- LOGIQUE DES PALIERS DE FIDÉLITÉ ---
            $discountPercent = 0;
            $pointsToUse = 0;

            if ($pointsAvailable >= 1000) {
                $discountPercent = 15;
                $pointsToUse = 1000;
            } elseif ($pointsAvailable >= 500) {
                $discountPercent = 7;
                $pointsToUse = 500;
            } elseif ($pointsAvailable >= 100) {
                $discountPercent = 1;
                $pointsToUse = 100;
            } else {
                $discountPercent = 0;
                $pointsToUse = 0;
            }

            // Calcul de la valeur en euros de la remise
            $discountValue = round($totalPrice * ($discountPercent / 100.0), 2);
            
            // Transmission des variables à la Vue HTML
            $this->data['discount_percent'] = $discountPercent; 
            $this->data['points_discount_value'] = $discountValue; 
            $this->data['points_to_use'] = $pointsToUse; 
            $this->data['final_price'] = max(0.0, round($totalPrice - $discountValue, 2));
        } else {
            // Invité/Guest non connecté
            $this->data['points_available'] = 0;
            $this->data['discount_percent'] = 0;
            $this->data['points_discount_value'] = 0.0;
            $this->data['points_to_use'] = 0;
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
            $totalPrice += (float)$item['prix_total'];
            $totalPointsEarned += (int)$item['nb_points'];
        }

        $discountPercent = 0;
        $pointsUsed = 0;

        if ($cliNum !== null) {
            require_once 'models/User/UserModel.php';
            $userModel = new \Models\UserModel();
            $user = $userModel->getUserById($cliNum);
            
            // Sécurité Oracle
            $pointsAvailable = (int)($user['CLI_NB_POINTS_EC'] ?? $user['cli_nb_points_ec'] ?? 0);
            
            // Application stricte des mêmes paliers côté serveur pour la validation
            if ($pointsAvailable >= 1000) {
                $discountPercent = 15;
                $pointsUsed = 1000;
            } elseif ($pointsAvailable >= 500) {
                $discountPercent = 7;
                $pointsUsed = 500;
            } elseif ($pointsAvailable >= 100) {
                $discountPercent = 1;
                $pointsUsed = 100;
            }
        }

        // Valeur brute de la remise globale
        $discountValue = $totalPrice * ($discountPercent / 100.0);
        $cart = $_SESSION['cart'];

        // 1. Vérification de disponibilité pour TOUS les trajets avant d'insérer
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

        // 2. Création des réservations
        foreach ($cart as $pending) {
            $itemOriginalPrice = (float)$pending['prix_total'];
            
            // Prorata de la réduction sur le ticket
            $itemDiscount = $totalPrice > 0 ? ($itemOriginalPrice / $totalPrice) * $discountValue : 0.0;
            
            // Correction ORA-01722 : On applique un round strict à 2 décimales
            $itemFinalPrice = round(max(0.0, $itemOriginalPrice - $itemDiscount), 2);

            // CASTING STRICT DE TOUS LES ENTIERS ET FLOTTANTS POUR LES VALEURS NUMÉRIQUES ORACLE
            $resNum = (int)$model->createReservation(
                $cliNum !== null ? (int)$cliNum : null,
                (int)$pending['tar_num_tranche'],
                (string)$pending['date'],
                (int)$pending['nb_points'],
                (float)$itemFinalPrice
            );

            // 3. Création de l'étape associée
            $model->createEtape(
                (string)$pending['lig_num'],
                (int)$resNum,
                $cliNum !== null ? (int)$cliNum : null,
                (string)$pending['code_depart'],
                (string)$pending['code_arrivee'],
                (float)$pending['distance'],
                (string)$pending['date'] . ' 00:00:00'
            );
        }

        // 4. Mise à jour des points de fidélité du client
        if ($cliNum !== null) {
            $netPoints = (int)$totalPointsEarned - (int)$pointsUsed;
            if ($netPoints !== 0 || $totalPointsEarned !== 0) {
                $model->updatePointsAfterReservation((int)$cliNum, (int)$totalPointsEarned, (int)$pointsUsed);
            }
        }

        // 5. Nettoyage du panier de session
        unset($_SESSION['cart']);

        $_SESSION['flash_success'] = 'Réservation confirmée avec succès !';
        session_write_close();
        redirect($cliNum !== null ? 'index.php?route=user/dashboard' : 'index.php');
    }
}