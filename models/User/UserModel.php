<?php

// models/User/UserModel.php

declare(strict_types=1);

namespace Models\User;

use Core\Model;

class UserModel extends Model
{
    /**
     * Récupère les informations complètes d'un utilisateur par son ID.
     */
    public function getUserById(int $cliNum): mixed
    {
        $sql = "SELECT c.cli_num, c.typ_num, c.cli_nom, c.cli_prenom, c.cli_courriel, c.cli_mdp, 
                       TO_CHAR(c.cli_date_connec, 'YYYY-MM-DD') AS cli_date_connec, 
                       c.cli_ville, c.cli_telephone, c.cli_nb_points_ec, c.cli_nb_points_tot, 
                       c.is_admin, c.is_deleted, t.* 
                FROM vik_client c
                LEFT JOIN vik_type_client t ON c.typ_num = t.typ_num
                WHERE c.cli_num = ?";
        return $this->fetch($sql, [$cliNum]);
    }

    /**
     * Récupère l'historique des réservations d'un utilisateur.
     */
    public function getUserReservations(int $cliNum): array
    {
        $sql = "SELECT r.res_num,
                       TO_CHAR(r.res_date, 'YYYY-MM-DD') as res_date,
                       r.res_prix_tot,
                       r.res_nb_points,
                       r.res_nb_points_dep,
                       (SELECT LISTAGG(c1.com_nom || ' → ' || c2.com_nom, ', ') WITHIN GROUP (ORDER BY e.eta_heure)
                        FROM vik_etape e
                        JOIN vik_commune c1 ON c1.com_code_insee = e.com_code_insee_depart
                        JOIN vik_commune c2 ON c2.com_code_insee = e.com_code_insee_arrivee
                        WHERE e.res_num = r.res_num AND e.cli_num = r.cli_num) AS trajets
                FROM vik_reservation r
                WHERE r.cli_num = ?
                ORDER BY r.res_date DESC";
        return $this->fetchAll($sql, [$cliNum]);
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

    /**
     * Marque le compte de l'utilisateur comme supprimé (Soft Delete).
     */
    public function deleteUser(int $cliNum): void
    {
        $sql = "UPDATE vik_client SET is_deleted = 1 WHERE cli_num = ?";
        $this->runQuery($sql, [$cliNum]);
    }
}
