<?php

// core/Database.php

declare(strict_types=1);

namespace Core;

use PDO;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct()
    {
        $dbConfig = Config::get('db');
        $this->pdo = new PDO("oci:dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}", $dbConfig['user'], $dbConfig['passwd']);
        $this->pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Forcer le point comme séparateur décimal pour éviter ORA-01722
        $this->pdo->exec("ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '. '");
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
    public function __destruct() {
        $this->pdo = null;
    }
}