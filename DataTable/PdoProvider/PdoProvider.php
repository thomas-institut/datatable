<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\PdoProvider;

use PDO;

interface PdoProvider
{
    public function getPdo(): PDO;
}
