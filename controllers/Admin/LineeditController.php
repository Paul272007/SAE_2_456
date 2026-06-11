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
        
        $this->data['all_lines'] = $linesModel->getLinesWithDetails();

        $ligNum = isset($_GET['id']) ? trim((string)$_GET['id']) : null;
        
        if ($ligNum) {
            $this->data['line'] = $scheduleModel->getLine($ligNum);
            $this->data['schedule'] = $scheduleModel->getSchedule($ligNum);
            $this->data['lig_num'] = $ligNum;
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

        $adminModel = new AdminModel();

        for ($i = 0; $i < count($arrets); $i++) {
            $codeArret = $arrets[$i];
            $oldH = $oldHeures[$i];
            $newH = $newHeures[$i];

            if ($oldH !== $newH && !empty($newH)) {
                $adminModel->updateScheduleTime($ligNum, $codeArret, $oldH, $newH);
            }
        }

        $_SESSION['flash_success'] = "Les horaires de la ligne {$ligNum} ont été mis à jour avec succès.";
        
        redirect('index.php?route=admin/lineedit&id=' . $ligNum);
    }
}