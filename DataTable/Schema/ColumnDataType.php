<?php

namespace ThomasInstitut\DataTable\Schema;

enum ColumnDataType : string
{
    /**
     * Any type that can be serialized and deserialized.
     *
     * Normally, the database will store the data as a serialized string.
     *
     * The database schema does not impose any restrictions on the type of data that can be stored in this column.
     */
    case Serializable = 'serializable';

    /**
     * A string of a maximum length. The column definition specifies the maximum length.
     */
    case VarChar = 'varchar';

    /*
     * A string of any length. No length check is performed.
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
     * The table's id column.
     */
    case Id = 'id';

}
