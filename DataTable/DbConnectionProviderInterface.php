<?php

namespace ThomasInstitut\DataTable;

use PDO;

interface DbConnectionProviderInterface
{
    public function getDbConnection(): PDO;
}