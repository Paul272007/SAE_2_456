<?php

declare(strict_types=1);

namespace Models;

use Core\Model;

class ReservationModel extends Model
{
    public function getLine(string $ligNum): mixed
    {
        $sql = 'SELECT TRIM(l.LIG_NUM) AS "lig_num",
                       c1.COM_NOM AS "commune_depart",
                       c2.COM_NOM AS "commune_arrivee"
                FROM VIK_LIGNE l
                JOIN VIK_COMMUNE c1 ON l.COM_CODE_INSEE_DEBU = c1.COM_CODE_INSEE
                JOIN VIK_COMMUNE c2 ON l.COM_CODE_INSEE_TERM = c2.COM_CODE_INSEE
                WHERE TRIM(l.LIG_NUM) = ?';

        return $this->fetch($sql, [trim($ligNum)]);
    }

    public function getStops(string $ligNum): array
    {
        $sql = 'SELECT TRIM(code) AS "code",
                       c.COM_NOM AS "nom",
                       heure_passage,
                       distance_prochain
                FROM (
                    SELECT n.COM_CODE_INSEE_ARRET AS code,
                           n.NOE_HEURE_PASSAGE AS heure_passage,
                           n.NOE_DISTANCE_PROCHAIN AS distance_prochain
                    FROM VIK_NOEUD n
                    WHERE TRIM(n.LIG_NUM) = ?
                    UNION
                    SELECT n.COM_CODE_INSEE_SUIVANT AS code,
                           n.NOE_HEURE_PASSAGE AS heure_passage,
                           0 AS distance_prochain
                    FROM VIK_NOEUD n
                    WHERE TRIM(n.LIG_NUM) = ?
                      AND n.COM_CODE_INSEE_SUIVANT IS NOT NULL
                      AND NOT EXISTS (
                          SELECT 1 FROM VIK_NOEUD n2
                          WHERE TRIM(n2.LIG_NUM) = TRIM(n.LIG_NUM)
                            AND TRIM(n2.COM_CODE_INSEE_ARRET) = TRIM(n.COM_CODE_INSEE_SUIVANT)
                      )
                ) stops
                JOIN VIK_COMMUNE c ON TRIM(stops.code) = c.COM_CODE_INSEE
                ORDER BY heure_passage ASC';

        return $this->fetchAll($sql, [trim($ligNum), trim($ligNum)]);
    }

    public function getSegmentDistance(string $ligNum, string $codeDepart, string $codeArrivee): float
    {
        $nodes = $this->getStops($ligNum);

        $distance = 0.0;
        $counting = false;

        foreach ($nodes as $node) {
            if (trim((string)$node['code']) === trim($codeDepart)) {
                $counting = true;
            }

            if ($counting) {
                $distance += (float)($node['distance_prochain'] ?? 0);
            }

            if (trim((string)$node['code']) === trim($codeArrivee)) {
                break;
            }
        }

        return $distance;
    }

    public function getTarif(float $distance): mixed
    {
        $sql = 'SELECT TAR_NUM_TRANCHE AS "tar_num_tranche",
                       TAR_PRIX AS "tar_prix"
                FROM VIK_TARIF
                WHERE TAR_MIN_DIST <= ? AND TAR_MAX_DIST >= ?
                ORDER BY TAR_NUM_TRANCHE ASC';

        return $this->fetch($sql, [$distance, $distance]);
    }

    public function getUniqueStops(string $ligNum): array
    {
        $sql = 'SELECT TRIM(code) AS "code",
                       c.COM_NOM AS "nom",
                       MIN(heure_passage) as min_heure
                FROM (
                    SELECT n.COM_CODE_INSEE_ARRET AS code,
                           n.NOE_HEURE_PASSAGE AS heure_passage
                    FROM VIK_NOEUD n
                    WHERE TRIM(n.LIG_NUM) = ?
                    UNION
                    SELECT n.COM_CODE_INSEE_SUIVANT AS code,
                           n.NOE_HEURE_PASSAGE AS heure_passage
                    FROM VIK_NOEUD n
                    WHERE TRIM(n.LIG_NUM) = ?
                      AND n.COM_CODE_INSEE_SUIVANT IS NOT NULL
                      AND NOT EXISTS (
                          SELECT 1 FROM VIK_NOEUD n2
                          WHERE TRIM(n2.LIG_NUM) = TRIM(n.LIG_NUM)
                            AND TRIM(n2.COM_CODE_INSEE_ARRET) = TRIM(n.COM_CODE_INSEE_SUIVANT)
                      )
                ) stops
                JOIN VIK_COMMUNE c ON TRIM(stops.code) = c.COM_CODE_INSEE
                GROUP BY TRIM(stops.code), c.COM_NOM
                ORDER BY MIN(heure_passage) ASC';

        return $this->fetchAll($sql, [trim($ligNum), trim($ligNum)]);
    }

    public function getAvailableSchedules(string $ligNum, string $codeDepart, string $codeArrivee, string $minTime): array
    {
        $sql = "SELECT TO_CHAR(nd.NOE_HEURE_PASSAGE, 'HH24:MI') as \"heure_depart\",
                       MIN(TO_CHAR(na.NOE_HEURE_PASSAGE, 'HH24:MI')) as \"heure_arrivee\"
                FROM VIK_NOEUD nd
                JOIN VIK_NOEUD na ON nd.LIG_NUM = na.LIG_NUM 
                WHERE TRIM(nd.LIG_NUM) = ?
                  AND TRIM(nd.COM_CODE_INSEE_ARRET) = ?
                  AND TRIM(na.COM_CODE_INSEE_ARRET) = ?
                  AND nd.NOE_HEURE_PASSAGE < na.NOE_HEURE_PASSAGE
                  AND TO_CHAR(nd.NOE_HEURE_PASSAGE, 'HH24:MI') >= ?
                GROUP BY TO_CHAR(nd.NOE_HEURE_PASSAGE, 'HH24:MI')
                ORDER BY \"heure_depart\" ASC";
                
        return $this->fetchAll($sql, [trim($ligNum), trim($codeDepart), trim($codeArrivee), $minTime]);
    }

    public function isAvailable(
        string $ligNum,
        string $codeDepart,
        string $codeArrivee,
        string $date,
        int $maxCapacity = 50
    ): bool {
        // Just checking if there is at least one schedule
        $schedules = $this->getAvailableSchedules($ligNum, $codeDepart, $codeArrivee, '00:00');
        return count($schedules) > 0;
    }
}