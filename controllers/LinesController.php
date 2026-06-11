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
            $line['stops'] = $scheduleModel->getSchedule((string)$line['lig_num']);
        }
        
        $this->data["lines"] = $lines;
        $this->render();
    }
}