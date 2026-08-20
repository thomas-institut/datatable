<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\Exception;

use RuntimeException;

/**
 * Exception thrown when a row from the database is invalid.
 *
 * This should normally mean a database error, so it is considered a run time error.
 */
class InvalidRowFromDatabase extends RuntimeException
{

}
