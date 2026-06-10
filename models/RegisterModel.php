<?php

// models/RegisterModel.php

declare(strict_types=1);

namespace Models;

use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Model;
use Exception;

class RegisterModel extends Model
{
    /**
     * Insère un nouveau client dans vik_client.
     * Paramètres attendus dans $params (dans l'ordre) :
     *   typ_num, dep_num, cli_nom, cli_prenom, cli_ville,
     *   cli_telephone, cli_courriel, cli_password, cli_date_connec
     * @throws Exception
     */
    public function register(array $params): void
    {
        $newId = $this->getLastClientId() + 1;

        $sql = "INSERT INTO vik_client(
                       cli_num,
                       typ_num,
                       dep_num,
                       cli_nom,
                       cli_prenom,
                       cli_ville,
                       cli_telephone,
                       cli_courriel,
                       cli_password,
                       cli_nb_points_ec,
                       cli_nb_points_tot,
                       cli_date_connec
                   ) VALUES ($newId, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)";

        $result = $this->runQuery($sql, $params);

        if (!$result) {
            throw new ClientError(ClientErrorCode::REGISTRATION_ERROR);
        }
    }

    /**
     * Vérifie si un client existe déjà avec cet email.
     * @throws Exception
     */
    public function userExists(string $email): bool
    {
        $sql    = "SELECT cli_courriel FROM vik_client WHERE cli_courriel = ?";
        $result = $this->fetch($sql, [$email]);
        return $result !== false;
    }

    /**
     * Retourne le cli_num le plus élevé (utilisé pour générer le prochain ID).
     * @throws Exception
     */
    public function getLastClientId(): int
    {
        $sql    = "SELECT MAX(cli_num) AS max_id FROM vik_client";
        $result = $this->fetch($sql);
        return (int)($result['max_id'] ?? 0);
    }
}