<?php

namespace Models;

use Core\Model;

class LinesModel extends Model
{   

    public function getLines(): array
    {
        $sql = "SELECT * FROM VIK_LIGNES";
        return $this->fetchAll($sql);
    }
}