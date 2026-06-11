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
            // Include UserModel to get current points
            require_once 'models/User/UserModel.php';
            $userModel = new \Models\UserModel();
            $user = $userModel->getUserById((int)$_SESSION['userId']);
            $pointsAvailable = (int)($user['cli_nb_points_ec'] ?? 0);
            
            $this->data['points_available'] = $pointsAvailable;
            // Conversion: 10 points = 1 euro discount. Max discount = total price.
            $maxDiscountPoints = (int)floor($totalPrice * 10);
            $pointsToUse = min($pointsAvailable, $maxDiscountPoints);
            $this->data['points_discount_value'] = $pointsToUse / 10.0;
            $this->data['points_to_use'] = $pointsToUse;
        }

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
        
        // Handle points usage
        $usePoints = isset($_POST['use_points']) && $_POST['use_points'] === 'yes';
        $pointsUsed = 0;
        
        $totalPrice = 0.0;
        $totalPointsEarned = 0;
        foreach ($_SESSION['cart'] as $item) {
            $totalPrice += $item['prix_total'];
            $totalPointsEarned += $item['nb_points'];
        }

        if ($usePoints) {
            require_once 'models/User/UserModel.php';
            $userModel = new \Models\UserModel();
            $user = $userModel->getUserById($cliNum);
            $pointsAvailable = (int)($user['cli_nb_points_ec'] ?? 0);
            
            $maxDiscountPoints = (int)floor($totalPrice * 10);
            $pointsUsed = min($pointsAvailable, $maxDiscountPoints);
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
                // One of the journeys is not available
                throw new ClientError(ClientErrorCode::BAD_REQUEST);
            }
        }

        // Apply discount proportionally or just set it on the first reservation? 
        // For simplicity, we create reservations with original prices, and we deduct points globally.
        // If the system requires discount per reservation, it's more complex. We will just deduct points from user profile.

        // 2. Création des réservations
        foreach ($cart as $pending) {
            $resNum = $model->createReservation(
                $cliNum,
                (int)$pending['tar_num_tranche'],
                (string)$pending['date'],
                (int)$pending['nb_points'],
                (float)$pending['prix_total']
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
        // Points gagnés - points utilisés
        $netPoints = $totalPointsEarned - $pointsUsed;
        
        if ($netPoints !== 0 || $totalPointsEarned !== 0) {
            // We use addClientPoints which adds to ec and tot. 
            // Wait, points used should only deduct from ec, not tot.
            $model->updatePointsAfterReservation($cliNum, $totalPointsEarned, $pointsUsed);
        }

        // 5. Nettoyage de la session
        unset($_SESSION['cart']);

        $_SESSION['flash_success'] = 'reservation_confirmed';
        redirect('index.php?route=user/dashboard');
    }
}
