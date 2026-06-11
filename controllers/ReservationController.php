<?php

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

    public function get(): void
    {
        $ligNum = isset($_GET['lig_num']) ? trim((string)$_GET['lig_num']) : null;

        if (!$ligNum) {
            redirect('index.php?route=lines');
        }

        $model = $this->model ?? new ReservationModel();

        $line = $model->getLine($ligNum);

        if (!$line) {
            redirect('index.php?route=lines');
        }

        $this->data['line'] = $line;
        $this->data['stops'] = $model->getStops($ligNum);
        $this->data['lig_num'] = $ligNum;
        $this->data['today'] = date('Y-m-d');
        $this->data['connected'] = isset($_SESSION['userId']);

        $this->render();
    }

    public function post(): void
    {
        $this->checkPostFields();

        $ligNum = trim((string)$_POST['lig_num']);
        $codeDepart = trim((string)$_POST['depart']);
        $codeArrivee = trim((string)$_POST['arrivee']);
        $date = trim((string)$_POST['date']);

        if ($codeDepart === '' || $codeArrivee === '' || $date === '') {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        if ($codeDepart === $codeArrivee) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        $model = new ReservationModel();

        if (!$model->isAvailable($ligNum, $codeDepart, $codeArrivee, $date)) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        $distance = $model->getSegmentDistance($ligNum, $codeDepart, $codeArrivee);

        if ($distance <= 0) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        $tarif = $model->getTarif($distance);

        if (!$tarif) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        $tarNumTranche = (int)$tarif['tar_num_tranche'];
        $prixTotal = (float)$tarif['tar_prix'];
        $nbPoints = max(1, (int)floor($distance / 10));

        $stops = $model->getStops($ligNum);
        $stopMap = array_column($stops, 'nom', 'code');
        $line = $model->getLine($ligNum);

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $_SESSION['cart'][] = [
            'lig_num' => $ligNum,
            'ligne_nom' => ($line['commune_depart'] ?? '') . ' → ' . ($line['commune_arrivee'] ?? ''),
            'code_depart' => $codeDepart,
            'code_arrivee' => $codeArrivee,
            'nom_depart' => $stopMap[$codeDepart] ?? $codeDepart,
            'nom_arrivee' => $stopMap[$codeArrivee] ?? $codeArrivee,
            'date' => $date,
            'distance' => $distance,
            'tar_num_tranche' => $tarNumTranche,
            'prix_total' => $prixTotal,
            'nb_points' => $nbPoints,
        ];

        $_SESSION['flash_success'] = "Trajet ajouté au panier.";
        redirect('index.php?route=reservation/cart');
    }
}