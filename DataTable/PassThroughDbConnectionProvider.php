<?php

namespace ThomasInstitut\DataTable;

use PDO;

readonly class PassThroughDbConnectionProvider implements DbConnectionProviderInterface
{
    public function __construct(private PDO $pdo)
    {
    }
    public function getDbConnection(): PDO
    {
        return $this->pdo;
    }
}