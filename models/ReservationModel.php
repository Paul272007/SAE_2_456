<?php

// models/ReservationModel.php

declare(strict_types=1);

namespace Models;

use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Model;
use Exception;

class ReservationModel extends Model
{
    /**
     * Retourne les informations d'une ligne.
     */
    public function getLine(string $ligNum): mixed
    {
        $sql = "SELECT l.lig_num,
                       c1.com_nom AS commune_depart,
                       c2.com_nom AS commune_arrivee,
                       l.com_code_insee_debu,
                       l.com_code_insee_term
                FROM vik_ligne l
                JOIN vik_commune c1 ON l.com_code_insee_debu = c1.com_code_insee
                JOIN vik_commune c2 ON l.com_code_insee_term = c2.com_code_insee
                WHERE TRIM(l.lig_num) = TRIM(?)";
        return $this->fetch($sql, [$ligNum]);
    }

    /**
     * Retourne toutes les stations d'une ligne triées par heure de passage.
     */
    public function getStops(string $ligNum): array
    {
        $sql = "SELECT n.com_code_insee_arret AS code,
                       c.com_nom              AS nom,
                       MIN(n.noe_heure_passage) AS noe_heure_passage
                FROM vik_noeud n
                JOIN vik_commune c ON n.com_code_insee_arret = c.com_code_insee
                WHERE TRIM(n.lig_num) = TRIM(?)
                GROUP BY n.com_code_insee_arret, c.com_nom
                ORDER BY MIN(n.noe_heure_passage) ASC";
        return $this->fetchAll($sql, [$ligNum]);
    }

    /**
     * Calcule la distance totale entre deux arrêts sur une ligne.
     * La distance est la somme des noe_distance_prochain depuis le départ jusqu'à l'arrivée
     * (excluant le nœud d'arrivée lui-même, qui commence le segment suivant).
     */
    public function getSegmentDistance(string $ligNum, string $codeDepart, string $codeArrivee): float
    {
        // Récupération de tous les nœuds de la ligne dans l'ordre de passage
        $sql = "SELECT com_code_insee_arret,
                       MAX(noe_distance_prochain) as noe_distance_prochain
                FROM vik_noeud
                WHERE TRIM(lig_num) = TRIM(?)
                GROUP BY com_code_insee_arret
                ORDER BY MIN(noe_heure_passage) ASC";
        $nodes = $this->fetchAll($sql, [$ligNum]);

        $distance = 0.0;
        $counting = false;

        foreach ($nodes as $node) {
            if ($node['com_code_insee_arret'] === $codeDepart) {
                $counting = true;
            }
            if ($counting) {
                $distance += (float)($node['noe_distance_prochain'] ?? 0);
            }
            if ($node['com_code_insee_arret'] === $codeArrivee) {
                break;
            }
        }

        return $distance;
    }

    /**
     * Trouve le tarif applicable pour une distance donnée.
     * Retourne le premier tarif dont la distance est dans la tranche.
     */
    public function getTarif(float $distance): mixed
    {
        $sql = "SELECT tar_num_tarif, tar_num_tranche, tar_prix
                FROM (
                    SELECT tar_num_tarif, tar_num_tranche, tar_prix
                    FROM vik_tarif
                    WHERE tar_min_dist <= ? AND tar_max_dist >= ?
                    ORDER BY tar_num_tranche ASC
                )
                WHERE ROWNUM <= 1";
        return $this->fetch($sql, [$distance, $distance]);
    }

    /**
     * Vérifie la disponibilité d'un segment sur l'ensemble du trajet.
     * Compte les réservations existantes sur ce segment à cette date.
     * Retourne true si des places sont disponibles.
     */
    public function isAvailable(string $ligNum, string $codeDepart, string $codeArrivee, string $date, int $maxCapacity = 50): bool
    {
        // Récupère les arrêts dans l'ordre
        $stops = $this->getStops($ligNum);
        $stopCodes = array_column($stops, 'code');

        $idxDepart  = array_search($codeDepart,  $stopCodes, true);
        $idxArrivee = array_search($codeArrivee, $stopCodes, true);

        if ($idxDepart === false || $idxArrivee === false || $idxDepart >= $idxArrivee) {
            return false;
        }

        // On compte les réservations qui se chevauchent avec notre segment
        // Une réservation chevauche si son segment d'étape intersecte le nôtre
        $sql = "SELECT COUNT(*) AS nb
                FROM vik_reservation r
                JOIN vik_etape e ON e.res_num = r.res_num AND TRIM(e.lig_num) = TRIM(?)
                JOIN vik_noeud nd ON TRIM(nd.lig_num) = TRIM(?) AND nd.com_code_insee_arret = e.com_code_insee_depart
                JOIN vik_noeud na ON TRIM(na.lig_num) = TRIM(?) AND na.com_code_insee_arret = e.com_code_insee_arrivee
                WHERE r.res_date = TO_DATE(?, 'YYYY-MM-DD')
                  AND nd.noe_heure_passage < na.noe_heure_passage";
        $result = $this->fetch($sql, [$ligNum, $ligNum, $ligNum, $date]);
        $existingCount = (int)($result['nb'] ?? 0);

        return $existingCount < $maxCapacity;
    }

    /**
     * Crée une réservation en base et retourne son numéro.
     * @throws Exception
     */
    public function createReservation(
        ?int   $cliNum,
        int    $tarNumTranche,
        string $date,
        int    $nbPoints,
        float  $prixTotal
    ): int {
        $maxSql    = "SELECT MAX(res_num) AS max_id FROM vik_reservation";
        $maxResult = $this->fetch($maxSql);
        $newId     = ((int)($maxResult['max_id'] ?? 0)) + 1;

        $sql = "INSERT INTO vik_reservation
                    (res_num, cli_num, tar_num_tranche, res_date, res_nb_points, res_prix_tot)
                VALUES (?, ?, ?, TO_DATE(?, 'YYYY-MM-DD'), ?, ?)";
        $result = $this->runQuery($sql, [$newId, $cliNum, $tarNumTranche, $date, $nbPoints, $prixTotal]);

        if (!$result) {
            throw new ClientError(ClientErrorCode::BAD_REQUEST);
        }

        return $newId;
    }

    /**
     * Crée une étape liée à une réservation.
     * @throws Exception
     */
    public function createEtape(
        string    $ligNum,
        int    $resNum,
        string $codeDepart,
        string $codeArrivee,
        float  $distance,
        string $heure
    ): void {
        $sql = "INSERT INTO vik_etape
                    (lig_num, res_num, com_code_insee_depart, com_code_insee_arrivee, eta_distance, eta_heure)
                VALUES (?, ?, ?, ?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS'))";
        $this->runQuery($sql, [$ligNum, $resNum, $codeDepart, $codeArrivee, $distance, $heure]);
    }

    /**
     * Met à jour les points du client après une réservation (points gagnés et points utilisés).
     * @throws Exception
     */
    public function updatePointsAfterReservation(int $cliNum, int $pointsEarned, int $pointsUsed): void
    {
        $sql = "UPDATE vik_client
                SET cli_nb_points_ec  = cli_nb_points_ec + ? - ?,
                    cli_nb_points_tot = cli_nb_points_tot + ?,
                    cli_date_connec   = SYSDATE
                WHERE cli_num = ?";
        $this->runQuery($sql, [$pointsEarned, $pointsUsed, $pointsEarned, $cliNum]);
    }
}
