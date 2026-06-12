<?php


declare(strict_types=1);

namespace Controllers\Admin;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\LinesModel;

#[RequirePrivilege(Privilege::ADMIN)]
class LinesController extends Controller
{
  public function get(): void
  {
    require_once 'models/LinesModel.php';
    require_once 'models/ScheduleModel.php';
    
    $model = new LinesModel();
    $scheduleModel = new \Models\ScheduleModel();

    $lines = $model->getLinesWithDetails();

    foreach ($lines as &$line) {
        $rawStops = $scheduleModel->getSchedule((string)$line['lig_num']);
        $stopsGrouped = [];
        foreach ($rawStops as $s) {
            $code = $s['com_code_insee_arret'];
            if (!isset($stopsGrouped[$code])) {
                $stopsGrouped[$code] = [
                    'code' => $code,
                    'arret_nom' => $s['arret_nom'],
                    'heures' => []
                ];
            }
            $stopsGrouped[$code]['heures'][] = [
                'old_heure' => (string)$s['noe_heure_passage'], // already in HH:MM format
                'value' => (string)$s['noe_heure_passage']
            ];
        }
        $line['stops'] = array_values($stopsGrouped);
    }

    $this->data['lines'] = $lines;
    $this->render();
  }

  public function post(): void
  {
      $ligNum = $_POST['lig_num'] ?? '';
      $arrets = $_POST['arret_code'] ?? [];
      $oldHeures = $_POST['old_heure'] ?? [];
      $newHeures = $_POST['new_heure'] ?? [];

      require_once 'models/Admin/AdminModel.php';
      $adminModel = new \Models\Admin\AdminModel();

      for ($i = 0; $i < count($arrets); $i++) {
          $codeArret = $arrets[$i];
          $oldH = $oldHeures[$i];
          $newH = $newHeures[$i];

          if ($oldH !== $newH && !empty($newH)) {
              $adminModel->updateScheduleTime($ligNum, $codeArret, $oldH, $newH);
          }
      }

      $_SESSION['flash_success'] = "Les horaires de la ligne {$ligNum} ont été mis à jour avec succès.";
      redirect('index.php?route=admin/lines');
  }
}

