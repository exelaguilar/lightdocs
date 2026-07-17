<?php
namespace System\Library;

use PDO;
use PDOStatement;
use PDOException;
use RuntimeException;
use stdClass;

/**
 * A database wrapper class utilizing PDO for safe and convenient database interactions.
 *
 * This class provides a simplified interface for executing prepared statements,
 * fetching results into a structured object, and handling database transactions.
 *
 * Lightdocs adaptation: the storage driver is SQLite (single-file database), so
 * the constructor takes a filesystem path instead of MySQL credentials. The
 * query()/row/rows/num_rows API is identical to the Nevernote/OpenCart core.
 *
 * @package System\Library
 * @author Exel
 */
class DB
{
    /**
     * @var PDO The active PDO connection instance.
     */
    private PDO $pdo;

    /**
     * @var PDOStatement|null The last executed PDOStatement object.
     */
    private ?PDOStatement $statement = null;

    /**
     * DB constructor.
     *
     * Establishes the SQLite database connection, creating the parent
     * directory when needed.
     *
     * @param string $path Filesystem path to the SQLite database file.
     */
    public function __construct(string $path)
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            $this->pdo = new PDO('sqlite:' . $path, null, null, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Could not connect to database: ' . $e->getMessage(), 0, $e);
        }

        $this->pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA synchronous = NORMAL; PRAGMA busy_timeout = 5000;');
    }

    /**
     * Returns the underlying PDO connection.
     *
     * Retained for Lightdocs content models that operate on the PDO handle
     * directly; new code should prefer query().
     *
     * @return PDO
     */
    public function connection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepares and executes a SQL query with bound parameters.
     *
     * Returns an object containing:
     * - rows: array of all result rows (assoc arrays)
     * - row: the first result row or null
     * - num_rows: number of rows returned (for SELECT)
     * - affected_rows: number of rows affected (for INSERT/UPDATE/DELETE)
     *
     * @param string $sql    The SQL query to execute.
     * @param array  $params An associative array of parameters to bind to the query.
     *
     * @return object The query result object.
     */
    public function query(string $sql, array $params = []): object
    {
        $this->statement = $this->pdo->prepare($sql);

        // Iterate over parameters to bind them explicitly with correct types
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $param_type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $param_type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $param_type = PDO::PARAM_NULL;
            } else {
                $param_type = PDO::PARAM_STR;
            }

            $bind_key = is_int($key) ? ($key + 1) : $key;

            $this->statement->bindValue($bind_key, $value, $param_type);
        }

        $this->statement->execute();

        $result = new stdClass();
        $result->rows = [];
        $result->row = null;

        if ($this->statement->columnCount() > 0) {
            $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);
            $result->rows = $rows;
            $result->row = $rows[0] ?? null;
        }

        $result->num_rows = count($result->rows);
        $result->affected_rows = $this->statement->rowCount();

        return $result;
    }

    /**
     * Escapes a string for inline interpolation into a SQL query string.
     *
     * @deprecated Prefer parameterised queries via query($sql, $params) instead.
     *
     * @param string $value The string to escape.
     * @return string The escaped string, WITHOUT surrounding quotes.
     */
    public function escape(string $value): string
    {
        return substr($this->pdo->quote($value), 1, -1);
    }

    /**
     * Returns the ID of the last inserted row.
     *
     * @return int The last insert ID.
     */
    public function getLastId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Returns the number of rows affected by the last executed statement.
     *
     * @return int The number of affected rows.
     */
    public function countAffected(): int
    {
        return $this->statement ? $this->statement->rowCount() : 0;
    }

    /**
     * Initiates a database transaction.
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commits the current database transaction.
     *
     * @return void
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Rolls back the current database transaction.
     *
     * @return void
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Returns true only when a live connection exists.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        if (!isset($this->pdo)) {
            return false;
        }

        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
