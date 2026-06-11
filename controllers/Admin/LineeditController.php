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
use Models\Admin\AdminModel;

#[RequirePrivilege(Privilege::ADMIN)]
class LineeditController extends Controller
{
    public function get(): void
    {
        $ligNum = isset($_GET['id']) ? trim((string)$_GET['id']) : null;
        if (!$ligNum) {
            redirect('index.php?route=admin/lines');
        }

        require_once 'models/ScheduleModel.php';
        $scheduleModel = new \Models\ScheduleModel();
        
        $this->data['line'] = $scheduleModel->getLine($ligNum);
        $fullSchedule = $scheduleModel->getSchedule($ligNum);
        $this->data['lig_num'] = $ligNum;

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
            
            $oldH_short = substr(trim($oldHeures[$i]), 0, 5);
            $newH_short = substr(trim($newHeures[$i]), 0, 5);

            if ($oldH_short !== $newH_short && !empty($newH_short)) {
                $adminModel->updateScheduleTime($ligNum, $codeArret, $oldH_short, $newH_short);
            }
        }

        $_SESSION['flash_success'] = "Les horaires ont été mis à jour avec succès.";
        
        $redirectUrl = 'index.php?route=admin/lineedit&id=' . urlencode($ligNum);
        if (!empty($selectedArret)) {
            $redirectUrl .= '&arret=' . urlencode($selectedArret);
        }
        redirect($redirectUrl);
    }
}