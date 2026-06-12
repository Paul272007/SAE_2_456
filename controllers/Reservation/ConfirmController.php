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
        
        $discountedPrice = $totalPrice;
        $gradeDiscountValue = 0.0;
        $gradeDiscountPercent = 0;

        if ($this->data['connected']) {
            $userModel = new UserModel();
            $reservationModel = new ReservationModel();
            
            $user = $userModel->getUserById((int)$_SESSION['userId']);
            $pointsAvailable = (int)($user['cli_nb_points_ec'] ?? 0);

            $typReduc = (int)($user['typ_reduc'] ?? 100);
            if ($typReduc === 0) $typReduc = 100;

            if ($typReduc > 0 && $typReduc < 100) {
                $gradeDiscountPercent = 100 - $typReduc;
                $discountedPrice = round($totalPrice * ($typReduc / 100.0));
                $gradeDiscountValue = $totalPrice - $discountedPrice;
            }

            $reductions = $reservationModel->getReductions();
            $bestReduction = null;
            
            foreach ($reductions as $red) {
                if ($pointsAvailable >= (int)$red['red_nb_points'] && $discountedPrice >= (float)$red['red_valeur']) {
                    $bestReduction = $red;
                    break;
                }
            }

            $this->data['points_available'] = $pointsAvailable;
            
            if ($bestReduction) {
                $this->data['points_discount_value'] = (float)$bestReduction['red_valeur'];
                $this->data['points_to_use'] = (int)$bestReduction['red_nb_points'];
            } else {
                $this->data['points_discount_value'] = 0;
                $this->data['points_to_use'] = 0;
            }
        }

        $this->data['grade_discount_percent'] = $gradeDiscountPercent;
        $this->data['grade_discount_value'] = $gradeDiscountValue;
        $this->data['final_price'] = round($discountedPrice); // Before points

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

        $cliNum = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : null;
        $model  = new ReservationModel();
        
        $totalPrice = 0.0;
        $totalPointsEarned = 0;
        foreach ($_SESSION['cart'] as $item) {
            $totalPrice += $item['prix_total'];
            $totalPointsEarned += $item['nb_points'];
        }
        
        $discountedPrice = $totalPrice;
        $pointsUsed = 0;
        
        if ($cliNum !== null) {
            $userModel = new UserModel();
            $user = $userModel->getUserById($cliNum);
            
            $typReduc = (int)($user['typ_reduc'] ?? 100);
            if ($typReduc === 0) $typReduc = 100;
            
            if ($typReduc > 0 && $typReduc < 100) {
                $discountedPrice = round($totalPrice * ($typReduc / 100.0));
            }

            $usePoints = isset($_POST['use_points']) && $_POST['use_points'] === 'yes';
            if ($usePoints) {
                $pointsAvailable = (int)($user['cli_nb_points_ec'] ?? 0);
                $reductions = $model->getReductions();
                
                foreach ($reductions as $red) {
                    if ($pointsAvailable >= (int)$red['red_nb_points'] && $discountedPrice >= (float)$red['red_valeur']) {
                        $pointsUsed = (int)$red['red_nb_points'];
                        $discountedPrice -= (float)$red['red_valeur'];
                        break;
                    }
                }
            }
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

        // Calcul du ratio de réduction pour répartir équitablement sur chaque trajet
        $discountRatio = $totalPrice > 0 ? $discountedPrice / $totalPrice : 1;

        // 2. Création des réservations
        foreach ($cart as $pending) {
            $itemFinalPrice = round((float)$pending['prix_total'] * $discountRatio);
            $resNum = $model->createReservation(
                $cliNum,
                (int)$pending['tar_num_tranche'],
                (string)$pending['date'],
                (int)$pending['nb_points'],
                $itemFinalPrice
            );

            // 3. Création de l'étape (segment réservé)
            $model->createEtape(
                (string)$pending['lig_num'],
                $resNum,
                $cliNum,
                (string)$pending['code_depart'],
                (string)$pending['code_arrivee'],
                (float)$pending['distance'],
                $pending['date'] . ' 00:00:00'
            );
        }

        // 4. Mise à jour des points de fidélité du client
        if ($cliNum !== null) {
            $netPoints = $totalPointsEarned - $pointsUsed;
            if ($netPoints !== 0 || $totalPointsEarned !== 0) {
                $model->updatePointsAfterReservation($cliNum, $totalPointsEarned, $pointsUsed);
            }
        }

        // 5. Nettoyage de la session
        unset($_SESSION['cart']);

        $_SESSION['flash_success'] = 'Réservation confirmée avec succès !';
        redirect($cliNum !== null ? 'index.php?route=user/dashboard' : 'index.php');
    }
}
