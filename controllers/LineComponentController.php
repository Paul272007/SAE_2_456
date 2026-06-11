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
     * @param int  $ligNum
     * @param string $terminus1
     * @param string $terminus2
     * @return array{line_number: int, line_terminus_1: string, line_terminus_2: string, stops: array}
     */
    public static function getComponentData(int $ligNum, string $terminus1, string $terminus2): array
    {
        // TODO: idem que getStops() — appeler le modèle avec la requête SQL
        // $model = new ScheduleModel();
        // $schedule = $model->getSchedule($ligNum);
        //
        // $stops = array_map(function (array $stop): array {
        //     return [
        //         'city'       => $stop['arret_nom'],
        //         'department' => '',
        //         'hours'      => [$stop['noe_heure_passage']],
        //     ];
        // }, $schedule);

        return [
            'line_number'     => $ligNum,
            'line_terminus_1' => $terminus1,
            'line_terminus_2' => $terminus2,
            'stops'           => [], // TODO: remplacer par $stops
        ];
    }
}
