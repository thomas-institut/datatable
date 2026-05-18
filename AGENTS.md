# AGENTS.md

## Project Overview

This project, `datatable`, is a PHP 8.3 library that provides an abstraction for data access and manipulation of SQL-like
tables. It decouples basic data functions from actual database details, allowing for flexible implementations (e.g.,
In-Memory for testing, MySQL for production) and supporting features like transactions and unitemporal data.

## Core Concepts

### 1. DataTable Interface

The central component is the `DataTable` interface (`DataTable/DataTable.php`). It defines standard operations for a
table where each row is identified by a unique integer ID. Key features include:

- **CRUD Operations**: `createRow`, `getRow`, `updateRow`, `deleteRow`.
- **Search/Query**: `findRows` (simple matching) and `search` (complex conditions with AND/OR).
- **Transactions**: `startTransaction`, `commit`, `rollBack`.
- **Metadata**: `getIdColumnName`, `setName`, etc.
- **Interfaces**: Implements `ArrayAccess`, `IteratorAggregate`, `LoggerAwareInterface`, and `ErrorReporter`.

### 2. Implementations

- **GenericDataTable**: A base class (`DataTable/GenericDataTable.php`) providing common logic for many `DataTable`
  methods.
- **InMemoryDataTable**: A non-persistent implementation (`DataTable/InMemoryDataTable.php`) using PHP arrays, ideal for
  unit testing.
- **MySqlDataTable**: A persistent implementation (`DataTable/MySqlDataTable.php`) using PDO and MySQL.
- **MySqlDataTableWithRandomIds**: A specialization of `MySqlDataTable` that uses random ID generation.

### 3. Unitemporal Support

The `UnitemporalDataTable` interface (`DataTable/UnitemporalDataTable.php`) extends `DataTable` to support time-tagged
rows. This allows:

- Retrieving versions of a row at a specific point in time (`getRowWithTime`).
- Maintaining a history of changes instead of physically deleting rows (`deleteRowWithTime` marks them as invalid).
- `MySqlUnitemporalDataTable` is the concrete implementation for MySQL.

### 4. ID Generation

The library uses `IdGenerator` (`DataTable/IdGenerator.php`) to handle row ID assignment:

- **SequentialIdGenerator**: Generates incremental IDs.
- **RandomIdGenerator**: Generates random IDs within a range, with a fallback to sequential if collisions occur
  frequently.

### 5. Results Iteration

Methods returning multiple rows (like `getAllRows` or `search`) return a `DataTableResultsIterator` (
`DataTable/DataTableResultsIterator.php`). This provides:

- Standard iteration via `foreach`.
- `count()` for the number of results.
- `getFirst()` for convenience.
- Specialized implementations: `DataTableResultsArrayIterator` and `DataTableResultsPdoIterator`.

## Key Dependencies

- **PSR-3 (psr/log)**: For logging support.
- **PDO**: For MySQL database connectivity.
- **Thomas-Institut Timestring**: For handling high-precision timestamps in unitemporal tables.

## Testing

The project includes a `test/` directory using PHPUnit, with mock classes to facilitate testing different
implementations.

Test should use features of PHPUnit 12

Do not use the local machine's PHP. The user must have started a development container with the appropiate PHP
version. Use the scripts in `scripts`:

- **Run all tests**: `scripts/dev-php-test`
- **Run phpunit** with arbitrary options:  `scripts/dev-phpunit --option1 --option2 ... test`

## Task Completion Requirements

Run `scripts/dev-php-test` and make sure all the tests pass. 