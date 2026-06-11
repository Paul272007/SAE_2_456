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

    public function isAvailable(
        string $ligNum,
        string $codeDepart,
        string $codeArrivee,
        string $date,
        int $maxCapacity = 50
    ): bool {
        $stops = $this->getStops($ligNum);
        $stopCodes = array_map('trim', array_column($stops, 'code'));

        $idxDepart = array_search(trim($codeDepart), $stopCodes, true);
        $idxArrivee = array_search(trim($codeArrivee), $stopCodes, true);

        if ($idxDepart === false || $idxArrivee === false || $idxDepart >= $idxArrivee) {
            return false;
        }

        return true;
    }
}