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
        $sql = "INSERT INTO users(user_name, password) VALUES (?, ?)";
        $result = $this->runQuery($sql, $params);

        if (!$result) {
            throw new ClientError(ClientErrorCode::REGISTRATION_ERROR);
        }
    }
}