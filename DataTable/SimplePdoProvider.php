<?php

namespace ThomasInstitut\DataTable;

use PDO;

// @codeCoverageIgnoreStart
readonly class SimplePdoProvider implements PdoProvider
{
    public function __construct(private PDO $pdo)
    {
    }
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
// @codeCoverageIgnoreEnd