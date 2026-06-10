<?php

// core/Language.php

declare(strict_types=1);

namespace Core;

class Language
{
    private static array $data;

    public static function load(string $language) : void
    {
        $file = 'languages/' . $language . '.php';
        if (file_exists($file))
            self::$data = require $file;
        else
            self::$data = require 'languages/en.php';
    }

    public static function get(string $key) : string
    {
        return self::$data[$key] ?? $key;
    }
}