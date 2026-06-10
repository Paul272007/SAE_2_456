<?php

// core/Database.php

declare(strict_types=1);

namespace Core;

use PDO;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dbConfig = Config::get('db');
        $this->pdo = new PDO("oci:dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}", $dbConfig['user'], $dbConfig['passwd']);
    }
    public static function getInstance(): Database
    {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}

