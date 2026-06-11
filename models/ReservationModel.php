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
                       c2.COM_NOM AS "commune_arrivee",
                       TRIM(l.COM_CODE_INSEE_DEBU) AS "code_debu",
                       TRIM(l.COM_CODE_INSEE_TERM) AS "code_term"
                FROM VIK_LIGNE l
                JOIN VIK_COMMUNE c1 ON l.COM_CODE_INSEE_DEBU = c1.COM_CODE_INSEE
                JOIN VIK_COMMUNE c2 ON l.COM_CODE_INSEE_TERM = c2.COM_CODE_INSEE
                WHERE TRIM(l.LIG_NUM) = ?';

        return $this->fetch($sql, [trim($ligNum)]);
    }

    public function getStops(string $ligNum): array
    {
        $sql = 'SELECT TRIM(n.COM_CODE_INSEE_ARRET) AS "code",
                       c.COM_NOM AS "nom",
                       TO_CHAR(n.NOE_HEURE_PASSAGE, \'HH24:MI\') AS "heure_passage",
                       n.NOE_DISTANCE_PROCHAIN AS "distance_prochain"
                FROM VIK_NOEUD n
                JOIN VIK_COMMUNE c ON n.COM_CODE_INSEE_ARRET = c.COM_CODE_INSEE
                WHERE TRIM(n.LIG_NUM) = ?
                ORDER BY n.NOE_HEURE_PASSAGE ASC';

        return $this->fetchAll($sql, [trim($ligNum)]);
    }

    private function resolveToNodeCode(string $ligNum, string $code): string
    {
        $exists = $this->fetch(
            "SELECT COUNT(*) AS cnt FROM VIK_NOEUD WHERE TRIM(LIG_NUM) = ? AND TRIM(COM_CODE_INSEE_ARRET) = ?",
            [trim($ligNum), trim($code)]
        );

        if ($exists && (int)$exists['cnt'] > 0) {
            return $code;
        }

        $line = $this->fetch(
            "SELECT TRIM(COM_CODE_INSEE_DEBU) AS debu, TRIM(COM_CODE_INSEE_TERM) AS term FROM VIK_LIGNE WHERE TRIM(LIG_NUM) = ?",
            [trim($ligNum)]
        );

        if ($line) {
            if (trim($code) === $line['debu']) {
                $first = $this->fetch(
                    "SELECT TRIM(COM_CODE_INSEE_ARRET) AS code FROM VIK_NOEUD WHERE TRIM(LIG_NUM) = ? ORDER BY NOE_HEURE_PASSAGE ASC",
                    [trim($ligNum)]
                );
                return $first ? $first['code'] : $code;
            }
            if (trim($code) === $line['term']) {
                $last = $this->fetch(
                    "SELECT TRIM(COM_CODE_INSEE_ARRET) AS code FROM VIK_NOEUD WHERE TRIM(LIG_NUM) = ? ORDER BY NOE_HEURE_PASSAGE DESC",
                    [trim($ligNum)]
                );
                return $last ? $last['code'] : $code;
            }
        }

        return $code;
    }

    public function getSegmentDistance(string $ligNum, string $codeDepart, string $codeArrivee): float
    {
        $codeDepart = $this->resolveToNodeCode($ligNum, $codeDepart);
        $codeArrivee = $this->resolveToNodeCode($ligNum, $codeArrivee);
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
        $sql = 'SELECT TRIM(n.COM_CODE_INSEE_ARRET) AS "code",
                       c.COM_NOM AS "nom",
                       MIN(n.NOE_HEURE_PASSAGE) as min_heure
                FROM VIK_NOEUD n
                JOIN VIK_COMMUNE c ON n.COM_CODE_INSEE_ARRET = c.COM_CODE_INSEE
                WHERE TRIM(n.LIG_NUM) = ?
                GROUP BY TRIM(n.COM_CODE_INSEE_ARRET), c.COM_NOM
                ORDER BY MIN(n.NOE_HEURE_PASSAGE) ASC';

        $stops = $this->fetchAll($sql, [trim($ligNum)]);

        $line = $this->getLine($ligNum);
        if ($line) {
            $existingCodes = array_map(fn($s) => trim($s['code']), $stops);

            if (!in_array(trim($line['code_debu']), $existingCodes)) {
                array_unshift($stops, [
                    'code' => $line['code_debu'],
                    'nom'  => $line['commune_depart'],
                ]);
            }

            if (!in_array(trim($line['code_term']), $existingCodes)) {
                $stops[] = [
                    'code' => $line['code_term'],
                    'nom'  => $line['commune_arrivee'],
                ];
            }
        }

        return $stops;
    }

    public function getAvailableSchedules(string $ligNum, string $codeDepart, string $codeArrivee, string $minTime): array
    {
        $codeDepart = $this->resolveToNodeCode($ligNum, $codeDepart);
        $codeArrivee = $this->resolveToNodeCode($ligNum, $codeArrivee);

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