<?php

// models/User/SettingsModel

declare(strict_types=1);

namespace Models\User;

use Core\Exceptions\ClientError;
use Core\Exceptions\ClientErrorCode;
use Core\Model;
use Exception;

class SettingsModel extends Model
{
    /**
     * @throws Exception
     */
    public function changeSettings(array $settings): void
    {
        $sql = "UPDATE users SET user_language = ? WHERE user_id = ?";
        $result = $this->runQuery($sql, $settings);

        if (!$result) {
            throw new ClientError(ClientErrorCode::SETTINGS_ERROR);
        }
    }
}