<?php

// core/Model.php

declare(strict_types=1);


namespace Core;

use Core\Exceptions\ServerError;
use Core\Exceptions\ServerErrorCode;
use Exception;
use PDO;
use PDOStatement;

class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * @throws Exception
     */
    protected function runQuery($query, $params = []) : PDOStatement | false
    {
        $stmt = $this->db->prepare($query);
        if (!$stmt)
            throw new ServerError(ServerErrorCode::SQL_ERROR, $query);

        if (!$stmt->execute($params))
            throw new ServerError(ServerErrorCode::SQL_ERROR, implode(", ", $stmt->errorInfo()));

        return $stmt;
    }

    /**
     * @throws Exception
     */
    protected function fetchAll($query, $params = []) : array
    {
        $stmt = $this->runQuery($query, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @throws Exception
     */
    protected function fetch($query, $params = []) : mixed
    {
        $stmt = $this->runQuery($query, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @throws Exception
     */
    public function userExists(string $email) : bool
    {
        $sql = "SELECT cli_courriel FROM vik_client WHERE cli_courriel = ?";
        $result = $this->fetch($sql, [$email]);
        return $result !== false;
    }

    /**
     * @throws Exception
     */
    public function getUserByEMail(string $email) : mixed
    {
        $sql = "SELECT * FROM vik_client WHERE cli_courriel = ?";
        return $this->fetch($sql, [$email]);
    }

    public function lastInsertId() : string | false
    {
        return $this->db->lastInsertId();
    }
}