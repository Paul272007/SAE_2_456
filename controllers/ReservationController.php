<?php

// controllers/ReservationController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use Exception;
use Models\ReservationModel;

#[RequirePrivilege(Privilege::GUEST)]
class ReservationController extends Controller
{
    protected static array $postFields = ['lig_num', 'depart', 'arrivee', 'date'];

    /**
     * Affiche le formulaire de réservation pour une ligne donnée (GET ?lig_num=X).
     */
    public function get(): void
    {
        $ligNum = isset($_GET['lig_num']) ? (int)$_GET['lig_num'] : null;

        if (!$ligNum) {
            redirect('index.php?route=lines');
        }

        /** @var ReservationModel $model */
        $model = $this->model ?? new ReservationModel();

        $line = $model->getLine($ligNum);
        if (!$line) {
            redirect('index.php?route=lines');
        }

        $this->data['line']       = $line;
        $this->data['stops']      = $model->getStops($ligNum);
        $this->data['lig_num']    = $ligNum;
        $this->data['csrf_token'] = $_SESSION['csrf_token'];
        $this->data['today']      = date('Y-m-d');
        $this->data['connected']  = isset($_SESSION['userId']);

        $this->render();
    }

    /**
     * Traite le formulaire de réservation (POST).
     * Calcule le prix, vérifie la disponibilité, stocke en session et redirige vers la confirmation.
     * @throws ClientError
     * @throws Exception
     */
    public function post(): void
    {
        verifyCSRFToken();
        $this->checkPostFields();

        $ligNum     = (int)$_POST['lig_num'];
        $codeDepart  = trim($_POST['depart']);
        $codeArrivee = trim($_POST['arrivee']);
        $date        = trim($_POST['date']);

        // Validation basique
        if ($codeDepart === $codeArrivee) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        $model = new ReservationModel();

        // 1. Vérification de la disponibilité sur l'ensemble du segment
        if (!$model->isAvailable($ligNum, $codeDepart, $codeArrivee, $date)) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        // 2. Calcul de la distance du segment
        $distance = $model->getSegmentDistance($ligNum, $codeDepart, $codeArrivee);

        if ($distance <= 0) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        // 3. Recherche du tarif applicable
        $tarif = $model->getTarif($distance);

        $tarNumTranche = $tarif['tar_num_tranche'] ?? 1;
        $prixTotal     = $tarif ? (float)$tarif['tar_prix'] : 0.0;

        // 4. Calcul des points (1 point par km)
        $nbPoints = (int)round($distance);

        // 5. Récupération des noms d'arrêts pour l'affichage
        $stops    = $model->getStops($ligNum);
        $stopMap  = array_column($stops, 'nom', 'code');
        $line     = $model->getLine($ligNum);

        // 6. Stockage de la réservation dans le panier en session
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $_SESSION['cart'][] = [
            'lig_num'        => $ligNum,
            'ligne_nom'      => ($line['commune_depart'] ?? '') . ' → ' . ($line['commune_arrivee'] ?? ''),
            'code_depart'    => $codeDepart,
            'code_arrivee'   => $codeArrivee,
            'nom_depart'     => $stopMap[$codeDepart]  ?? $codeDepart,
            'nom_arrivee'    => $stopMap[$codeArrivee] ?? $codeArrivee,
            'date'           => $date,
            'distance'       => $distance,
            'tar_num_tranche'=> $tarNumTranche,
            'prix_total'     => $prixTotal,
            'nb_points'      => $nbPoints,
        ];

        $_SESSION['flash_success'] = "Trajet ajouté au panier.";
        redirect('index.php?route=reservation/cart');
    }
}
