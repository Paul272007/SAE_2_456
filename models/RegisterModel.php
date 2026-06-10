<?php

// models/Guest/RegisterModel

declare(strict_types=1);

namespace Models;

use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Model;
use Exception;

class RegisterModel extends Model
{
    /**
     * @throws Exception
     */
    public function register(array $params): void
    {
        $maxIdSql = "SELECT MAX(cli_num) as max_id FROM vik_client";
        $maxIdResult = $this->fetch($maxIdSql);
        $newId = ($maxIdResult['max_id'] ?? 0) + 1;

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
}