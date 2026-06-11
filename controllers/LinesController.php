<?php

// controllers/LinesController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\LinesModel;

#[RequirePrivilege(Privilege::GUEST)]
class LinesController extends Controller
{
    public function get(): void
    {
        $this->model = new LinesModel();
        $lines = $this->model->getLinesWithDetails();
        
        require_once 'models/ScheduleModel.php';
        $scheduleModel = new \Models\ScheduleModel();

        foreach ($lines as &$line) {
            $rawStops = $scheduleModel->getSchedule((string)$line['lig_num']);
            
            $stopsGrouped = [];
            foreach ($rawStops as $s) {
                $code = $s['com_code_insee_arret'];
                if (!isset($stopsGrouped[$code])) {
                    $stopsGrouped[$code] = [
                        'arret_nom' => $s['arret_nom'],
                        'heures' => []
                    ];
                }
                $stopsGrouped[$code]['heures'][] = substr((string)$s['noe_heure_passage'], 0, 5); // Assuming format HH:MM:SS or HH:MM
            }
            
            $line['stops'] = array_values($stopsGrouped);
        }
        
        $this->data["lines"] = $lines;
        $this->render();
    }
}