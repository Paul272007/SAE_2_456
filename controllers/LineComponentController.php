<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Privilege;
use Core\RequirePrivilege;
use Models\ScheduleModel;

#[RequirePrivilege(Privilege::GUEST)]
class LineComponentController extends Controller
{
    public function get(): void
    {
        $model = new ScheduleModel;

        $ligNum    = (string) ($_GET['lig_num']);
        $terminus1 = $_GET['terminus_1'] ?? '';
        $terminus2 = $_GET['terminus_2'] ?? '';

        $stops = $model->getStopsHours($ligNum);

        $this->data['stylesheet']      = '/styles/Lines.css';
        $this->data['javascript']      = '/scripts/Lines.js';
        $this->data['line_number']     = $ligNum;
        $this->data['line_terminus_1'] = $terminus1;
        $this->data['line_terminus_2'] = $terminus2;
        $this->data['stops']           = $stops;

        $this->render();
    }

    /**
     * @param string  $ligNum
     * @param string $terminus1
     * @param string $terminus2
     * @return array{line_number: int, line_terminus_1: string, line_terminus_2: string, stops: array}
     */
    public static function getComponentData(string $ligNum, string $terminus1, string $terminus2): array
    {
        $model = new ScheduleModel();
        $stops = $model->getStopsHours($ligNum);
        
        return [
            'line_number'     => $ligNum,
            'line_terminus_1' => $terminus1,
            'line_terminus_2' => $terminus2,
            'stops'           => $stops,
        ];
    }
}
