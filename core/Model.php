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
    public function userExists($username) : bool
    {
        $sql = "SELECT user_name FROM users WHERE user_name = ?";
        $user = $this->fetch($sql, [$username]);
        return $user != false;
    }

    /**
     * @throws Exception
     */
    public function getUserByUsername($username) : mixed
    {
        $sql = "SELECT * FROM users WHERE user_name = ?";
        return $this->fetch($sql, [$username]);
    }

    public function lastInsertId() : string | false
    {
        return $this->db->lastInsertId();
    }
}