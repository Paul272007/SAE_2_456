<?php

// controllers/Admin/LineeditController.php

declare(strict_types=1);

namespace Controllers\Admin;

use Core\Controller;
use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\ScheduleModel;
use Models\LinesModel;
use Models\Admin\AdminModel;

#[RequirePrivilege(Privilege::ADMIN)]
class LineeditController extends Controller
{
    public function get(): void
    {
        require_once 'models/ScheduleModel.php';
        require_once 'models/LinesModel.php';
        
        $scheduleModel = new \Models\ScheduleModel();
        $linesModel = new \Models\LinesModel();
        
        // 1. Charger toutes les lignes pour le premier menu déroulant
        $this->data['all_lines'] = $linesModel->getLinesWithDetails();

        $ligNum = isset($_GET['id']) ? trim((string)$_GET['id']) : null;
        $this->data['lig_num'] = $ligNum;

        if ($ligNum) {
            $this->data['line'] = $scheduleModel->getLine($ligNum);
            $fullSchedule = $scheduleModel->getSchedule($ligNum);

            // 2. Extraire la liste unique des arrêts pour le second menu déroulant
            $uniqueStops = [];
            foreach ($fullSchedule as $stop) {
                $code = $stop['com_code_insee_arret'];
                if (!isset($uniqueStops[$code])) {
                    $uniqueStops[$code] = [
                        'code' => $code,
                        'nom'  => $stop['arret_nom']
                    ];
                }
            }
            $this->data['unique_stops'] = array_values($uniqueStops);

            // 3. Filtrer le planning si un arrêt spécifique a été sélectionné
            $selectedArret = isset($_GET['arret']) ? trim((string)$_GET['arret']) : null;
            $this->data['selected_arret'] = $selectedArret;

            if ($selectedArret) {
                $filteredSchedule = array_filter($fullSchedule, function($stop) use ($selectedArret) {
                    return $stop['com_code_insee_arret'] === $selectedArret;
                });
                $this->data['schedule'] = $filteredSchedule;
            } else {
                $this->data['schedule'] = $fullSchedule; 
            }
        }

        $this->render();
    }

    public function post(): void
    {
        $ligNum = $_POST['lig_num'] ?? '';
        if (empty($ligNum)) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        $arrets = $_POST['arret_code'] ?? [];
        $oldHeures = $_POST['old_heure'] ?? [];
        $newHeures = $_POST['new_heure'] ?? [];
        $selectedArret = $_POST['selected_arret'] ?? '';

        $adminModel = new AdminModel();

        for ($i = 0; $i < count($arrets); $i++) {
            $codeArret = $arrets[$i];
            $oldH = trim($oldHeures[$i]);
            $newH = trim($newHeures[$i]);

            // Forcer l'ajout des secondes pour Oracle si le navigateur ne renvoie que HH:mm
            if (strlen($oldH) === 5) {
                $oldH .= ':00';
            }
            if (strlen($newH) === 5) {
                $newH .= ':00';
            }

            // Mise à jour uniquement si l'horaire a été modifié
            if ($oldH !== $newH && !empty($newH)) {
                $adminModel->updateScheduleTime($ligNum, $codeArret, $oldH, $newH);
            }
        }

        $_SESSION['flash_success'] = "Les horaires ont été mis à jour avec succès.";
        
        // Redirection en gardant l'arrêt sélectionné
        $redirectUrl = 'index.php?route=admin/lineedit&id=' . urlencode($ligNum);
        if (!empty($selectedArret)) {
            $redirectUrl .= '&arret=' . urlencode($selectedArret);
        }
        redirect($redirectUrl);
    }
}