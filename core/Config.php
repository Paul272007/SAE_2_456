<?php

// core/Config.php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\ServerError;
use Core\Exceptions\ServerErrorCode;

class Config
{
    private static array $settings = [];

    /**
     * @throws ServerError
     */
    public static function load(string $path): void {
        if (!file_exists($path))
            throw new ServerError(ServerErrorCode::CONFIG_NOT_FOUND, $path);
        if (empty(self::$settings)) {
            self::$settings = json_decode(file_get_contents($path), true);
        }
    }

    public static function get(string $key)
    {
        return self::$settings[$key] ?? null;
    }

    public static function getAll(): array
    {
        return self::$settings;
    }
}