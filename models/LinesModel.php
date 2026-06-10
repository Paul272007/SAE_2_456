<?php

namespace Models;

use Core\Model;

class LinesModel extends Model
{
    public function getLines(): array
    {
        $sql = "SELECT DISTINCT
                       l.LIG_NUM,
                       l.LIG_NOM,
                       n.COM_CODE_INSEE_ARRET,
                       c.COM_NOM,
                       d.DEP_NOM,
                       n.NOE_HEURE_PASSAGE
                FROM VIK_LIGNES l
                JOIN VIK_NOEUD n ON n.LIG_NUM = l.LIG_NUM
                JOIN VIK_COMMUNE c ON c.COM_CODE_INSEE = n.COM_CODE_INSEE_ARRET
                JOIN VIK_DEPARTEMENT d ON d.DEP_NUM = c.DEP_NUM
                ORDER BY l.LIG_NUM, c.COM_NOM, n.NOE_HEURE_PASSAGE";

        $rows = $this->fetchAll($sql);

        return $this->groupLines($rows);
    }

    private function groupLines(array $rows): array
    {
        $lines = [];

        foreach ($rows as $row) {
            $lid = $row['LIG_NUM'];

            if (!isset($lines[$lid])) {
                $lines[$lid] = [
                    'num_ligne' => $lid,
                    'nom_ligne' => $row['LIG_NOM'],
                    'stops'     => [],
                ];
            }

            $insee = $row['COM_CODE_INSEE_ARRET'];

            if (!isset($lines[$lid]['stops'][$insee])) {
                $lines[$lid]['stops'][$insee] = [
                    'com_nom'     => $row['COM_NOM'],
                    'dep_nom'     => $row['DEP_NOM'],
                    'horaires'    => [],
                ];
            }

            if ($row['NOE_HEURE_PASSAGE'] !== null) {
                $lines[$lid]['stops'][$insee]['horaires'][] = $this->formatTime($row['NOE_HEURE_PASSAGE']);
            }
        }

        foreach ($lines as &$line) {
            $line['stops'] = array_values($line['stops']);
        }

        return array_values($lines);
    }

    private function formatTime(mixed $time): string
    {
        if ($time instanceof \DateTimeInterface) {
            return $time->format('G\hi');
        }

        if (is_string($time)) {
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
                $parts = explode(':', $time);
                return $parts[0] . 'h' . $parts[1];
            }
            if (preg_match('/^\d{1,2}h\d{2}$/', $time)) {
                return $time;
            }
            return $time;
        }

        return (string) $time;
    }
}
