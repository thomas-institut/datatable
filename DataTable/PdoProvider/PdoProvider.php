<?php

namespace ThomasInstitut\DataTable\PdoProvider;

use PDO;

interface PdoProvider
{
    public function getPdo(): PDO;
}