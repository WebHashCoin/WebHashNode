<?php

namespace WebHash\Network\Database\entities;

use WebHash\Network\Database\DatabaseConnection;

abstract class AbstractEntity
{

    protected DatabaseConnection $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

}
