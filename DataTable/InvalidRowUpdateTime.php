<?php

namespace ThomasInstitut\DataTable;

/**
 * Exception to get thrown when a given update time for a row is invalid:
 *   the update time must be later than the start of the row's last version
 *   validity.
 */
class InvalidRowUpdateTime extends UnitemporalDataTableException
{

}