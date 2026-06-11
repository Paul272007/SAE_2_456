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
        $lines = $this->model->getLines();

        foreach ($lines as &$line) {
            $componentData = LineComponentController::getComponentData(
                $line['lig_num'],
                $line['commune_depart'],
                $line['commune_arrivee']
            );
            $line['stops'] = $componentData['stops'];
        }
        unset($line);

        $this->data["lines"] = $lines;
        $this->render();
    }
}