# DataTable

[![Latest Stable Version](https://poser.pugx.org/thomas-institut/datatable/v/stable)](https://packagist.org/packages/thomas-institut/datatable)
[![License](https://poser.pugx.org/thomas-institut/datatable/license)](https://packagist.org/packages/thomas-institut/datatable)

This package defines abstract data access and manipulation of SQL-like tables (tables made out of rows with a unique integer id as 
its key), and provides in-memory and MySQL implementations of that abstraction. The purpose is to decouple basic data functions
from actual database details and to allow for faster testing by using in-memory tables.

## Installation 

Install the latest version with

```bash
$ composer require thomas-institut/datatable
```

## Usage

### DataTable 
The main component of this package is the `DataTable` interface, which captures the basic functionality
of an SQL table with unique integer ids. Implementations deal with the underlying storage,
which can be shared among different DataTable instances. Basic id generation mechanisms are
provided as alternatives to sequential ids. 

```
$dt = new DataTableImplementationClass(...) 
```
`DataTable` objects also implement the `ArrayAccess`, `IteratorAggregate`, `LoggerAwareInterface`
 and `ErrorReporter` interfaces.

The default id generation mechanism behaves exactly like auto-increment in databases, but it is
handled without database intervention. Alternative id generators can be devised and set with 
`setIdGenerator`.

The `MySqlDataTable` implementation provided in this package can also defer id generation to MySql's 
AUTO INCREMENT functionality, and this is preferred over DataTable's default implementation.
 
A random id generator is provided. This generator will try to generate random Ids between two given 
values to up to a maximum number of attempts, after which it will default to the maximum current id plus 1:
```
$dt->setIdGenerator(new RandomIdGenerator($min, $max, $maxAttempts));
```
If proper values of `$min`, `$max` and `$maxAttempts` are chosen, it can be practically
impossible for the random id generator to go to the default.

#### Error Handling and Reporting 

Invalid argument errors are handled by custom exceptions (see each
method's documentation for details) and all other problems normally result in
a RunTimeException being thrown. 

The latest error can be inspected by calling the `getErrorCode` and `getErrorMessage` methods.
These two methods are defined in the `ErrorReporter` interface.

Per the `LoggerAwareInteface`, a PSR Logger can be set as well and implementations should normally
report all errors here too. 

#### Row Creation
```
$newRow = [ 'field1' => 'exampleStringValue', // or  
            'field2' => null, 
            'field3' => true, // or false
            'field4' => $someIntegerVariable
            // ... 
             'fieldN' => $nthValue ];  
             
$newId  = $dt->createRow($newRow);  

// $newId is unique within the table
```

If an id is present in the row used for creation it will be used as the new id and 
a `RowAlreadyExists` exception will be thrown if that id is in use.  

The default name for the id field/column is `id`, if the underlying table in actual storage
uses a different name, this can be set setIdColumnName, normally right after construction:

```
$dt = new DataTableImplementation();
$dt->setIdColumnName('row_id'); 

$dt->getIdColumnName();  // 'row_id'
```

Setting the column name in a DataTable does not change anything in the underlying database. It only
tells the DataTable what the actual database id name is.

Array access can also be used to create new rows:

``` 
$dt[] = $newRow; 

// new row with a given id 
$dt[$desiredId] = $newRow;
// but this updates the row if $desiredId already exists
```

Any number of fields/column and types can be used in the creation row as long as this makes
sense with the implementation and actual storage. It's up to the implementation to ignore extra data
or throw an error.

#### Read / Search Rows

To check whether a row exists:
``` 
$result = $dt->rowExists($rowId);
``` 

To get a particular row:
```  
$row = $dt->getRow($rowId);  
//  $row === [ 'id' => $rowId, 'col1' => value, .... 'colN' => value]; 

// OR

$row = $dt[$rowId];
  
// both throw a RowDoesNotExist exception if the row does not exist
``` 

To get all rows:
```
$rows = $dt->getAllRows();
```
All methods that may return multiple rows return an `DataTableIterator` object.
This is a normal PHP iterator that can be used in a `foreach` statement extended with 
a `count()` method that returns the number of results. 

The iterator also provides a `getFirst()` convenience method that returns the first 
result in the set. However, there is no guarantee that a `foreach` statement on the iterator 
will work after `getFirst()` or that `getFirst()` will work after a `foreach` because 
rewinding might not be possible in particular implementations.  

```
$rows = $dt->getAllRows();

$numRows = $rows->count();

// EITHER 

foreach($rows as $row) {
   // do something ...
} 

// OR

$firstRow = $rows->getFirst(); // null if there are no rows in the result set
    
```

The `search` method performs a general search on the DataTable based on an 
array of search conditions and a search type according to the following rules:
```
public function search(array $searchSpecArray, int $searchType = self::SEARCH_AND, int $maxResults = 0) : array;

/**
  * Searches the datatable according to the given $searchSpec
  *
  * $searchSpecArray is an array of conditions.
  *
  * If $searchType is SEARCH_AND, the row must satisfy:
  *      $searchSpecArray[0] && $searchSpecArray[1] && ...  && $searchSpecArray[n]
  *
  * if  $searchType is SEARCH_OR, the row must satisfy the negation of the spec:
  *
  *      $searchSpecArray[0] || $searchSpecArray[1] || ...  || $searchSpecArray[n]
  *
  *
  * A condition is an array of the form:
  *
  *  $condition = [
  *      'column' => 'columnName',
  *      'condition' => one of (EQUAL_TO, NOT_EQUAL_TO, LESS_THAN, LESS_OR_EQUAL_TO, GREATER_THAN, GREATER_OR_EQUAL_TO)
  *      'value' => someValue
  * ]
  *
  * Notice that each condition type has a negation:
  *      EQUAL_TO  <==> NOT_EQUAL_TO
  *      LESS_THAN  <==>  GREATER_OR_EQUAL_TO
  *      LESS_OR_EQUAL_TO <==> GREATER_THAN
  *
  * if $maxResults > 0, an array of max $maxResults will be returned
  * if $maxResults <= 0, all results will be returned
```

Most often, the simpler utility method `findRows` can be used for simple matching of 
columns and values. 
```
public function findRows(array $rowToMatch, int $maxResults = 0) : array;
```
If a row matches the value for every key in `$rowToMatch`, it is returned as
part of the result set. This is equivalent to do an AND search with EQUAL_TO conditions
for every key in `$rowToMatch`  

#### Update Rows
```
$dt->updateRow($row);

// OR 

$dt[$rowId] = $row;

```
In `updateRow` the given row must have an id field that corresponds to a row in the table or 
else a `RowDoesNotExist` exception will be thrown. In the array access version, the
given `$rowId` will be used regardless of whether there is an id field in the given row.

Only the fields in `$row` are updated. An  incomplete row may produce errors if the 
underlying database schema expects values for those columns. 

#### Delete Rows
```
$result = $dt->deleteRow($rowId);

// OR

unset($dt[$rowId]);
```

The result is the number of columns affected, which is 0 if the row did not exist
in the first place.

#### Transactions

DataTables provide a basic interface to underlying database transaction capabilities,
if they exist.

To check if transactions are supported:

``` 
$supported = $dt->supportsTransactions(); // true if supported
```

If transactions are supported, they can be started with `startTransaction()` and 
ended with `commit()` or `rollBack()`

``` 
if($dt->supportsTransactions()){
  if ($dt->startTransaction()) {
  
    // create, update,delete rows ...
    // decide whether to commit or rollback

    if ($goAheadWithCommit)  {  
       if ($dt->commit()){  
          // all went well, changes are committed
       } else {
          // error during commit
          $errorMessage = $dt->getErrorMessage();
          $errorCode = $dt->getErrorCode();
       }
    } else {  // roll back
       if ($dt->rollBack() {
          // rollBack done, changes to the database not saved
       } else {
          // error during rollBack       
          $errorMessage = $dt->getErrorMessage();
          $errorCode = $dt->getErrorCode();
       }
    }
  } else {
     // error starting transaction
     $errorMessage = $dt->getErrorMessage();
     $errorCode = $dt->getErrorCode();
  }
}
```

There is also a convenience method to have the DataTable check if the underlying
database is in a transaction:

```
$result = $dt->isUnderlyingDatabaseInTransaction();
```

Extra care should be taken when working with transactions, especially if a 
database connection is shared among different DataTables. DataTables will not
start a transaction if the underlying database reports that a transaction is
currently going on (which may or may not be reliable depending on the database).
Also, commits can only be executed on the DataTable that initiated the transaction.

If a DataTable does not support transactions, `startTransaction()`,  `commit()` and 
`rollBack()`  will always return `false`.

 

### InMemoryDataTable

A `DataTable` implementation using simple PHP arrays, no storage. This makes
it possible to perform tests on data tables without having to set up
a database. 

### MySqlDataTable

A `DataTable` implementation using a MySQL table. 

```
$dt = new MySqlDataTable($pdoDatabaseConnection, $mySqlTableName, $useAutoInc, $idColumnName);
```

`MySqlDataTable` assumes that there is a table setup with at least
an int or bigint `id` column with the given name (`$idColumnName`, which defaults to 'id'). 

If `$useAutoInc` is true, `MySqlDataTable` assumes that the `id` column has 
the `AUTO_INCREMENT` attribute and will create rows so that MySQL will take care 
of generating IDs. Otherwise, `MySqlDataTable` itself takes care of generating 
incremental IDs. 

For compatibility with previous versions of this library, `$useAutoInc` defaults to false.
However, it is recommended that you use MySQL auto-increment functionality. In a
scenario where there are multiple calls to `createRow` concurrently, `DataTable`'s 
internal ID generator may fail to generate a unique id.  

The table in MySQL can have any number of extra columns of any type. As long
as calls to `createRow` and `updateRow` agree with columns names and types, everything
should work fine. You can also have default values defined in MySQL and leave
those out when calling `createRow`.

### MySqlDataTableWithRandomIds

The same as `MySqlDatable` but using the randomId generator.


### MySqlUnitemporalDataTable

A MySQL table with time-tagged rows. Every row not only has a unique id, but
also a valid_from and a valid_until time. When using the normal `DataTable` methods
`MySQLUnitemporalDataTable` behaves exactly the same as MySqlDataTable, but
it does not delete any rows, it just makes them invalid.

There is a set of time methods to create, read, update and delete
 previous versions of the data. For example:

``` 
$dt = new MySqlUnitemporalDataTable($pdoDatabaseConnection, $mySqlTableName);


$oldRow = $dt->getRowWithTime($rowId, $timeString);
```
`$timeString ` is a string formatted as a valid MySQL datetime with microseconds, 
e.g. `'2018-01-01  12:00:00.123123'` Use the static methods in the `TimeString`
class to generate such strings from MySQL date and datetime strings, and from UNIX
timestamps with or without microseconds.  

The underlying MySQL table must have two datetime fields: `valid_from` and 
`valid_until`

The user is responsible for setting the PDO connection with the timezone
that is going to be used in all queries using time parameters. 
