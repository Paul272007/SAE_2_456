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
use Models\User\UserModel; // Assuming we'll create this

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
            
            // On récupère le solde de points du client
            $pointsAvailable = (int)($user['cli_nb_points_ec'] ?? 0);
            $this->data['points_available'] = $pointsAvailable;
            
            // Implémentation des paliers de réduction (0%, 1%, 7%, 15%)
            $discountPercent = 0;
            if ($pointsAvailable >= 1000) {
                $discountPercent = 15;
            } elseif ($pointsAvailable >= 500) {
                $discountPercent = 7;
            } elseif ($pointsAvailable >= 100) {
                $discountPercent = 1;
            }

            // Calcul de la valeur de la réduction et du prix final
            $discountValue = $totalPrice * ($discountPercent / 100.0);
            
            $this->data['discount_percent'] = $discountPercent;
            $this->data['points_discount_value'] = $discountValue;
            $this->data['final_price'] = $totalPrice - $discountValue;
        } else {
            // Utilisateur non connecté : pas de réduction
            $this->data['discount_percent'] = 0;
            $this->data['points_discount_value'] = 0;
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

        // Si non connecté → sauvegarder et rediriger vers l'inscription
        if (!isset($_SESSION['userId'])) {
            $_SESSION['flash_info'] = 'Créez un compte pour finaliser votre réservation.';
            redirect('index.php?route=register');
        }

        $cliNum = (int)$_SESSION['userId'];
        $model  = new ReservationModel();
        
        $totalPrice = 0.0;
        $totalPointsEarned = 0;
        foreach ($_SESSION['cart'] as $item) {
            $totalPrice += $item['prix_total'];
            $totalPointsEarned += $item['nb_points'];
        }

        // On recalcule le pourcentage pour appliquer la bonne remise en base (sécurité côté serveur)
        require_once 'models/User/UserModel.php';
        $userModel = new \Models\UserModel();
        $user = $userModel->getUserById($cliNum);
        $pointsAvailable = (int)($user['cli_nb_points_ec'] ?? 0);
        
        $discountPercent = 0;
        if ($pointsAvailable >= 1000) {
            $discountPercent = 15;
        } elseif ($pointsAvailable >= 500) {
            $discountPercent = 7;
        } elseif ($pointsAvailable >= 100) {
            $discountPercent = 1;
        }

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
            // On calcule le prix final du trajet avec le pourcentage de réduction
            $itemOriginalPrice = (float)$pending['prix_total'];
            $itemDiscountValue = $itemOriginalPrice * ($discountPercent / 100.0);
            $itemFinalPrice = $itemOriginalPrice - $itemDiscountValue;

            $resNum = $model->createReservation(
                $cliNum,
                (int)$pending['tar_num_tranche'],
                (string)$pending['date'],
                (int)$pending['nb_points'],
                $itemFinalPrice // Insertion du prix réduit en BDD
            );

            // 3. Création de l'étape (segment réservé)
            $model->createEtape(
                (string)$pending['lig_num'],
                $resNum,
                (string)$pending['code_depart'],
                (string)$pending['code_arrivee'],
                (float)$pending['distance'],
                (string)$pending['date'] . ' 00:00:00'
            );
        }

        // 4. Mise à jour des points de fidélité du client
        // Avec ce système de paliers, le client NE PERD PAS de points (pointsUsed = 0), il gagne juste ceux du trajet actuel.
        $pointsUsed = 0;
        if ($totalPointsEarned !== 0) {
            $model->updatePointsAfterReservation($cliNum, $totalPointsEarned, $pointsUsed);
        }

        // 5. Nettoyage de la session
        unset($_SESSION['cart']);

        $_SESSION['flash_success'] = 'reservation_confirmed';
        redirect('index.php?route=user/dashboard');
    }
}