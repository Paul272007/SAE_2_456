<?php

// controllers/ScheduleController.php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Exception;
use Models\ScheduleModel;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[RequirePrivilege(Privilege::GUEST)]
class ScheduleController extends Controller
{
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     * @throws Exception
     */
    public function get(): void
    {
        // Pour être sûr que c'est un string
        $ligNum = isset($_GET['lig_num']) ? (string)$_GET['lig_num'] : null;

        if (!$ligNum) {
            redirect('index.php?route=lines');
        }

        /** @var ScheduleModel $model */
        $model = $this->model ?? new ScheduleModel();

        $line = $model->getLine($ligNum);

        if (!$line) {
            redirect('index.php?route=lines');
        }

        $this->data['line']     = $line;
        $this->data['schedule'] = $model->getSchedule($ligNum);
        $this->data['lig_num']  = $ligNum;

        $this->render();
    }
}
