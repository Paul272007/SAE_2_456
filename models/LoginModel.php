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
                       cli_password,
                       cli_nb_points_ec,
                       cli_nb_points_tot,
                       typ_num
                FROM vik_client
                WHERE cli_courriel = ?";
        return $this->fetch($sql, [$email]);
    }
}