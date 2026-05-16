<?php

namespace ThomasInstitut\DataTable;

use PDO;

interface PdoProvider
{
    public function getPdo(): PDO;
}