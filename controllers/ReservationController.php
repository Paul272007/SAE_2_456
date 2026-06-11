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

        $codeDepart = trim((string)($_GET['depart'] ?? ''));
        $codeArrivee = trim((string)($_GET['arrivee'] ?? ''));
        $date = trim((string)($_GET['date'] ?? ''));
        $time = trim((string)($_GET['time'] ?? ''));

        if ($codeDepart !== '' && $codeArrivee !== '' && $date !== '') {
            if ($codeDepart === $codeArrivee) {
                $this->data['error'] = "La ville de départ ne peut pas être identique à la ville d'arrivée.";
            } else if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
                $this->data['error'] = "La date sélectionnée n'est pas valide.";
            } else {
                $schedules = $model->getAvailableSchedules($ligNum, $codeDepart, $codeArrivee, $time);
                if (empty($schedules)) {
                    $this->data['error'] = "Aucun trajet disponible pour cette date à partir de cette heure.";
                } else {
                    $this->data['schedules'] = $schedules;
                    
                    $distance = $model->getSegmentDistance($ligNum, $codeDepart, $codeArrivee);
                    $tarif = $model->getTarif($distance);
                    if ($tarif) {
                        $this->data['prix'] = (float)$tarif['tar_prix'];
                    }
                }
            }
        }

        $this->data['line'] = $line;
        $this->data['stops'] = $model->getUniqueStops($ligNum);
        $this->data['lig_num'] = $ligNum;
        $this->data['today'] = date('Y-m-d');
        $this->data['connected'] = isset($_SESSION['userId']);
        
        $this->data['post_depart'] = $codeDepart;
        $this->data['post_arrivee'] = $codeArrivee;
        
        if ($date !== '') {
            $this->data['post_date'] = $date;
        }
        if ($time !== '') {
            $this->data['post_time'] = $time;
        }

        $this->render();
    }

    public function post(): void
    {
        $ligNum = trim((string)($_POST['lig_num'] ?? ''));
        $codeDepart = trim((string)($_POST['depart'] ?? ''));
        $codeArrivee = trim((string)($_POST['arrivee'] ?? ''));
        $date = trim((string)($_POST['date'] ?? ''));
        
        if (!isset($_POST['heure_depart']) || $codeDepart === '' || $codeArrivee === '' || $date === '') {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        $model = new ReservationModel();
        
        $heureDepart = trim((string)$_POST['heure_depart']);
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
        $nbPoints = (int)floor($distance / 10);

        $stops = $model->getUniqueStops($ligNum);
        $stopMap = array_column($stops, 'nom', 'code');
        $line = $model->getLine($ligNum);

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $_SESSION['cart'][] = [
            'lig_num' => $ligNum,
            'ligne_nom' => $line['lig_num'] ?? $ligNum,
            'code_depart' => $codeDepart,
            'code_arrivee' => $codeArrivee,
            'nom_depart' => $stopMap[$codeDepart] ?? $codeDepart,
            'nom_arrivee' => $stopMap[$codeArrivee] ?? $codeArrivee,
            'date' => $date,
            'heure_depart' => $heureDepart,
            'distance' => $distance,
            'tar_num_tranche' => $tarNumTranche,
            'prix_total' => $prixTotal,
            'nb_points' => $nbPoints,
        ];

        $_SESSION['flash_success'] = "Trajet ajouté au panier.";
        redirect('index.php?route=reservation/cart');
    }
}