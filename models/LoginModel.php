<?php

// models/LoginModel.php

declare(strict_types=1);

namespace Models;

use Core\Model;

class LoginModel extends Model
{
    /**
     * Recherche un client dans vik_client par son adresse email (cli_courriel).
     */
    public function getUserByEmail(string $email): mixed
    {
        $sql = "SELECT cli_num,
                       cli_nom,
                       cli_prenom,
                       cli_courriel,
                       cli_mdp,
                       cli_nb_points_ec,
                       cli_nb_points_tot,
                       typ_num,
                       is_admin,
                       is_deleted,
                       TO_CHAR(cli_date_connec, 'YYYY-MM-DD') AS cli_date_connec
                FROM vik_client
                WHERE cli_courriel = ?";
        return $this->fetch($sql, [$email]);
    }

    /**
     * Met à jour la date de dernière connexion du client.
     */
    public function updateLastConnection(int $cliNum): void
    {
        $sql = "UPDATE vik_client SET cli_date_connec = TO_DATE(?, 'YYYY-MM-DD') WHERE cli_num = ?";
        $this->runQuery($sql, [date('Y-m-d'), $cliNum]);
    }

    /**
     * Réinitialise les points en cours du client à 0.
     */
    public function resetCurrentPoints(int $cliNum): void
    {
        $sql = "UPDATE vik_client SET cli_nb_points_ec = 0 WHERE cli_num = ?";
        $this->runQuery($sql, [$cliNum]);
    }

    /**
     * Récupère tous les types de clients triés par points limites décroissants.
     */
    public function getClientTypes(): array
    {
        $sql = "SELECT typ_num, typ_nom, typ_pt_limite FROM vik_type_client WHERE typ_pt_limite IS NOT NULL ORDER BY typ_pt_limite DESC";
        return $this->fetchAll($sql);
    }

    /**
     * Met à jour le grade d'un client.
     */
    public function updateUserType(int $cliNum, int $typNum): void
    {
        $sql = "UPDATE vik_client SET typ_num = ? WHERE cli_num = ?";
        $this->runQuery($sql, [$typNum, $cliNum]);
    }
}