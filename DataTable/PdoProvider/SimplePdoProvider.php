<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\PdoProvider;

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
