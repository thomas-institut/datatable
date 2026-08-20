<?php

declare(strict_types=1);

namespace ThomasInstitut\DataTable\Schema;

enum ColumnDataType : string
{
    /**
     * Any type that can be serialized and deserialized.
     *
     * Normally, the database will store the data as a serialized string.
     *
     * The database schema does not impose any restrictions on the type of data that can be stored in this column.
     *
     * **WARNING**: This type must be marked as required since most databases would not accept a default value for it.
     */
    case Serializable = 'serializable';

    /**
     * A string of a maximum length. The column definition specifies the maximum length.
     */
    case VarChar = 'varchar';

    /**
     * A string of any length. No length check is performed.
     *
     * **WARNING**: This type must be marked as required since most databases would not accept a default value for it.
     *
     * @see ColumnDataType::VarChar an alternative that accepts a default value.
     */
    case Text = 'text';

    /**
     * An integer. No range check is performed.
     */
    case Integer = 'integer';

    /**
     * A boolean value: true or false.
     *
     * Depending on the actual implementation, this will be translated to a database-specific boolean type.
     */
    case Boolean = 'boolean';

    /**
     * The table's id column, a bigint
     */
    case Id = 'id';

    /**
     * The valid from column of a Unitemporal DataTable, a TimeString
     *
     */
    case ValidFrom = 'valid_from';

    /**
     * The valid until column of a Unitemporal DataTable, a TimeString
     */
    case ValidUntil = 'valid_until';

    /**
     * A time string (v1.3)
     *
     * DataTable 3.0 will use TimeString version 2.
     *
     * @see https://github.com/thomas-institut/timestring
     */
    case TimeString = 'time_string';

    /**
     * Types that do not accept a default value and therefore MUST be marked as required in the database schema.
     */
    const array NoDefaultTypes = [ColumnDataType::Serializable, ColumnDataType::Text];

}
