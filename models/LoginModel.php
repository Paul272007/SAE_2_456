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
                       typ_num
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
}