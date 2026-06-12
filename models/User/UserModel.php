<?php

// models/User/UserModel.php

declare(strict_types=1);

namespace Models;

use Core\Model;

class UserModel extends Model
{
    /**
     * Récupère les informations complètes d'un utilisateur par son ID.
     */
    public function getUserById(int $cliNum): mixed
    {
        $sql = "SELECT cli_num,
                       typ_num,
                       dep_num,
                       cli_nom,
                       cli_prenom,
                       cli_ville,
                       cli_telephone,
                       cli_courriel,
                       cli_nb_points_ec,
                       cli_nb_points_tot,
                       cli_date_connec
                FROM vik_client
                WHERE cli_num = ?";
        return $this->fetch($sql, [$cliNum]);
    }

    /**
     * Récupère le solde de points actuel du client.
     */
    public function getClientPoints(int $cliNum): int
    {
        $sql = "SELECT cli_nb_points_ec FROM vik_client WHERE cli_num = ?";
        $result = $this->fetch($sql, [$cliNum]);
        return $result ? (int)$result['cli_nb_points_ec'] : 0;
    }

    /**
     * Récupère l'historique des réservations d'un utilisateur avec les points dépensés.
     */
    public function getUserReservations(int $cliNum): array
    {
        $sql = "SELECT r.res_num,
                       TO_CHAR(r.res_date, 'YYYY-MM-DD') as res_date,
                       r.res_prix_tot,
                       r.res_nb_points,
                       (SELECT LISTAGG(c1.com_nom || ' → ' || c2.com_nom, ', ') WITHIN GROUP (ORDER BY e.eta_heure)
                        FROM vik_etape e
                        JOIN vik_commune c1 ON c1.com_code_insee = e.com_code_insee_depart
                        JOIN vik_commune c2 ON c2.com_code_insee = e.com_code_insee_arrivee
                        WHERE e.res_num = r.res_num AND e.cli_num = r.cli_num) AS trajets
                FROM vik_reservation r
                WHERE r.cli_num = ?
                ORDER BY r.res_date DESC";

        $rows = $this->fetchAll($sql, [$cliNum]);

        $soldeCourant = $this->getClientPoints($cliNum);
        $pointsDispo = $soldeCourant;

        for ($i = 0; $i < count($rows); $i++) {
            $row = &$rows[$i];

            $pointsDepensesA = min(
                $pointsDispo + $row['res_nb_points'],
                $row['res_prix_tot'] * 10
            );
            $pointsAvantA = $pointsDispo + $pointsDepensesA - $row['res_nb_points'];

            $pointsAvantB = $pointsDispo - $row['res_nb_points'];

            if ($pointsAvantA >= 0) {
                $row['nb_points_depenser'] = $pointsDepensesA;
                $pointsDispo = $pointsAvantA;
            } else {
                $row['nb_points_depenser'] = 0;
                $pointsDispo = $pointsAvantB;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Met à jour les informations de l'utilisateur.
     */
    public function updateUser(int $cliNum, array $data): void
    {
        $sql = "UPDATE vik_client
                SET cli_nom = ?,
                    cli_prenom = ?,
                    cli_ville = ?,
                    cli_telephone = ?
                WHERE cli_num = ?";
        $this->runQuery($sql, [
            $data['cli_nom'],
            $data['cli_prenom'],
            $data['cli_ville'],
            $data['cli_telephone'],
            $cliNum
        ]);
    }

    /**
     * Met à jour le mot de passe de l'utilisateur.
     */
    public function updatePassword(int $cliNum, string $hashedPassword): void
    {
        $sql = "UPDATE vik_client
                SET cli_mdp = ?
                WHERE cli_num = ?";
        $this->runQuery($sql, [$hashedPassword, $cliNum]);
    }
}
