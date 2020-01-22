# DataTable

[![Latest Stable Version](https://poser.pugx.org/rafaelnajera/datatable/v/stable)](https://packagist.org/packages/rafaelnajera/datatable)
[![License](https://poser.pugx.org/rafaelnajera/datatable/license)](https://packagist.org/packages/rafaelnajera/datatable)

An abstraction of an SQL-like table made out of rows with an unique integer id as 
its key. The package provides in-memory and MySQL implementations.


## Installation 

Install the latest version with

```bash
$ composer require thomas-institut/datatable
```

## Usage

### DataTable 
The main interface is the `DataTable` class, which captures the basic functionality
of an SQL table with unique ids. Implementations take care of creating new
unique ids and of interfacing with the underlying storage mechanism.

```
$dt = new DataTableDescendantClass(...) 
```

The default id generation mechanism behaves exactly like auto-increment in databases.
Use `setIdGenerator` to install an alternative generator.
 
A random Id generator is provided. This generator will try to assign random Ids to 
new rows between two given values up to a maximum number of attempts, after which it
will default to the maximum current Id plus 1:
```
$dt->setIdGenerator(new RandomIdGenerator($min, $max, $maxAttempts));
```
If proper values of $min, $max and $maxAttempts are chosen, it can be practically
impossible for the random Id generator to go to the default.

#### Error Handling 

Most methods will throw a RunTime exception if there was any problem. 
The latest error can be inspected by calling the `getErrorCode` and `getErrorMessage` 
methods.

#### Create Rows
```
$newRow = [ 'field1' => 'string value', 
            'field2' => 'string value', 
             'field3' => numberValue ];  
$newId  = $dt->createRow($newRow);  // $newId is unique but not necessarily sequential

```

You can use any number of fields and types as longs as this makes
sense with the implementation. An SQL implementation, for instance, 
may require that the fields agree in number and type with the underlying
SQL table. 

#### Read Rows

Check whether a row exists
``` 
$result = $dt->rowExists($rowId);
``` 

Get a particular row or all rows 
```  
$row = $dt->getRow($newId);  
// throws an InvalidArgument Exception if the row does not exist

$rows = $dt->getAllRows();  
// returns an array of rows (not necessary ordered by id)
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

The utility method `findRows` can be used for simple matching of columns and values. 
```
public function findRows(array $rowToMatch, int $maxResults = 0) : array;
```
If a row matches the value for every key in `$rowToMatch`, it is returned as
part of the result set. This is equivalent to do an AND search with EQUAL_TO conditions
for every key in `$rowToMatch`  

#### Update Rows
```
$result = $dt->updateRow($row);
```
The given row must have an id that corresponds to an row in the table. Only
the fields in `$row` are updated. An incomplete row may produce errors if the 
underlying database schema expects values for those columns. 

#### Delete Row
```
$result = $dt->deleteRow($rowId);
```

### InMemoryDataTable

A `DataTable` implementation using simple PHP arrays, no storage. This makes
it possible to perform tests on data tables without having to set up
a database. 

### MySqlDataTable

A `DataTable` implementation using a MySQL table. 

```
$dt = new MySqlDataTable($pdoDatabaseConnection, $mySqlTableName);
```

`MySqlDataTable` assumes that there is a table setup with at least 
an integer id column without autoincrement. `MySqlDataTable` itself takes
care of generating new incremental ids. 

The table in MySQL can have any number of extra columns of any type. As long
as calls to `createRow` and `updateRow` agree with columns names and types, everything
should work fine. You can even have default values defined in MySQL and leave
those out when calling `createRow`.

### MySqlDataTableWithRandomIds

The same as `MySqlDatable` but using the randomId generator.


### MySqlUnitemporalDataTable

A MySQL table with time-tagged rows. Every row not only has a unique id, but
also a valid_from and a valid_until time. When using the normal `DataTable` methods
`MySQLUnitemporalDataTable` behaves exactly the same as MySqlDataTable but
it does not delete any rows, it just makes them invalid.

There is a set of time methods to create, read, update and delete
 previous versions of the data. For example:

``` 
$dt = new MySqlUnitemporalDataTable($pdoDatabaseConnection, $mySqlTableName);


$oldRow = $dt->getRowWithTime($rowId, $timeString);
```
`$timeString ` is a string formatted as a valid MySQL datetime with microseconds, 
e.g. `'2018-01-01  12:00:00.123123'` Use the static methods in the `TimeString`
to generate such strings from MySQL date and datetime strings, and from UNIX
timestamps with or without microseconds.  

The underlying MySQL table must have two datetime fields: `valid_from` and 
`valid_until`

The user is responsible for setting the PDO connection with the timezone
that is going to be used in all queries using time parameters. 



