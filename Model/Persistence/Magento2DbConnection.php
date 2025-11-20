<?php

namespace BigBridge\ProductImport\Model\Persistence;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use PDO;

/**
 * Wrapper class for the PDO object that Magento 2 uses.
 *
 * Check for information https://phpdelusions.net/pdo
 *
 * @author Patrick van Bergen
 */
class Magento2DbConnection
{
    const SLOW = 0.1;

    const kB = 1024;
    const kB_max = 16384;

    // magnitudes as powers of two
    const _1_KB = 1;
    const _2_KB = 2;
    const _16_KB = 16;
    const _128_KB = 128;

    // 1 MB (smallest packet size) / 16 (bytes per id)
    const DELETES_PER_CHUNK = 65536;

    // 1 MB / about 270 bytes per path
    const REQUEST_PATHS_PER_CHUNK = 3500;

    const MAX_CONNECTION_RETRIES = 10;

    /** @var ResourceConnection $connection */
    protected $connection;

    /** @var  PDO */
    protected $pdo;

    /** @var bool Debug option: print slow queries */
    protected $echoSlowQueries = false;

    /** @var int MySQL maximum allowed packet (in kB) */
    protected $maxAllowedPacket;

    public function __construct(ResourceConnection $connection)
    {
        $this->connection = $connection;

        $this->connect();
    }

    protected function connect()
    {
        /** @var Mysql $mysql */
        $mysql = $this->connection->getConnection();

        /** @var PDO $pdo */
        $this->pdo = $mysql->getConnection();

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->maxAllowedPacket = $this->calculateMaxAllowedPacket();
    }

    protected function calculateMaxAllowedPacket()
    {
        // ask MySQL server about its biggest allowed packet (convert to kB)
        $maxAllowedPacket = (int)floor($this->fetchSingleCell("SELECT @@max_allowed_packet") / self::kB);

        // between 1024 kB and 16384 kB
        $maxAllowedPacket = max(self::kB, $maxAllowedPacket);
        $maxAllowedPacket = min($maxAllowedPacket, self::kB_max);

        return $maxAllowedPacket;
    }

    /**
     * Prepares and executes an SQL query or statement
     *
     * @param string $query
     * @param array $values
     * @return \PDOStatement
     */
    public function execute(string $query, $values = [])
    {
        $connectionErrors = [
            2006, // SQLSTATE[HY000]: General error: 2006 MySQL server has gone away
            2013,  // SQLSTATE[HY000]: General error: 2013 Lost connection to MySQL server during query
            4031, // SQLSTATE[HY000]: General error: 4031 The client was disconnected by server because of inactivity.
        ];
        $triesCount = 0;

        do {
            $retry = false;
            try {
                return $this->executeQuery($query, $values);
            } catch (\Exception $e) {
                /** @var $pdoException \PDOException */
                $pdoException = null;
                if ($e instanceof \PDOException) {
                    $pdoException = $e;
                } elseif (($e instanceof Zend_Db_Statement_Exception)
                    && ($e->getPrevious() instanceof \PDOException)
                ) {
                    $pdoException = $e->getPrevious();
                }

                // Check to reconnect
                if ($pdoException && $triesCount < self::MAX_CONNECTION_RETRIES
                    && in_array($pdoException->errorInfo[1], $connectionErrors)
                ) {
                    $retry = true;
                    $triesCount++;

                    $this->connection->closeConnection();

                    $this->connect();
                }

                if (!$retry) {
                    throw $e;
                }
            }
        } while ($retry);
    }

    /**
     * Prepares and executes an SQL query or statement
     *
     * @param string $query
     * @param array $values
     * @return \PDOStatement
     */
    protected function executeQuery(string $query, $values = [])
    {
#echo $query . "\n";

        if ($this->echoSlowQueries) {

            $a = microtime(true);

            $st = $this->pdo->prepare($query);
            $st->execute($values);

            $b = microtime(true);
            if ($b - $a > self::SLOW) {
                echo ($b - $a) . ": " . substr($query, 0, 1000) . "\n";
            }

        } else {

            $st = $this->pdo->prepare($query);
            $st->execute($values);

        }

        return $st;
    }

    /**
     * Insert multiple rows at once, passing a single 1 dimensional array of $values
     *
     * @param string $table
     * @param array $columns
     * @param array $values
     * @param int $magnitude
     */
    public function insertMultiple(string $table, array $columns, array $values, int $magnitude)
    {
        $this->chunkedGroupExecute("
            INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`)
            VALUES {{marks}}",
            $columns, $values, $magnitude
        );
    }

    /**
     * Replace multiple rows at once, passing a single 1 dimensional array of $values
     *
     * @param string $table
     * @param array $columns
     * @param array $values
     * @param int $magnitude
     */
    public function replaceMultiple(string $table, array $columns, array $values, int $magnitude)
    {
        $this->chunkedGroupExecute("
            REPLACE INTO `{$table}` (`" . implode('`, `', $columns) . "`)
            VALUES {{marks}}",
            $columns, $values, $magnitude
        );
    }

    /**
     * Insert multiple rows at once, passing a single 1 dimensional array of $values
     * Performs an ON DUPLICATE KEY UPDATE with $updateClause
     *
     * @param string $table
     * @param array $columns
     * @param array $values
     * @param int $magnitude
     * @param string $updateClause
     */
    public function insertMultipleWithUpdate(string $table, array $columns, array $values, int $magnitude, string $updateClause)
    {
        $this->chunkedGroupExecute("
            INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`)
            VALUES {{marks}}
            ON DUPLICATE KEY UPDATE {$updateClause}",
            $columns, $values, $magnitude
        );
    }

    /**
     * Insert multiple rows at once, passing a single 1 dimensional array of $values
     * Adds IGNORE to INSERT
     *
     * @param string $table
     * @param array $columns
     * @param array $values
     * @param int $magnitude
     */
    public function insertMultipleWithIgnore(string $table, array $columns, array $values, int $magnitude)
    {
        $this->chunkedGroupExecute("
            INSERT IGNORE INTO `{$table}` (`" . implode('`, `', $columns) . "`)
            VALUES {{marks}}",
            $columns, $values, $magnitude
        );
    }

    /**
     * Deletes multiple rows at once, using $keys
     *
     * @param string $table
     * @param string $keyColumn
     * @param array $keys
     */
    public function deleteMultiple(string $table, string $keyColumn, array $keys)
    {
        foreach (array_chunk($keys, self::DELETES_PER_CHUNK) as $chunk) {
            $this->execute("
                DELETE FROM`{$table}`
                WHERE `{$keyColumn}` IN (" . $this->getMarks($chunk) . ")",
                $chunk);
        }
    }

    /**
     * Deletes multiple rows at once, using primary $keys
     * Adds an extra WHERE $whereClause
     *
     * @param string $table
     * @param string $keyColumn
     * @param array $keys
     * @param string $whereClause
     */
    public function deleteMultipleWithWhere(string $table, string $keyColumn, array $keys, string $whereClause)
    {
        foreach (array_chunk($keys, self::DELETES_PER_CHUNK) as $chunk) {
            $this->execute("
                DELETE FROM `{$table}`
                WHERE `{$keyColumn}` IN (?" . str_repeat(',?', count($chunk) - 1) . ") AND {$whereClause}",
                $chunk);
        }
    }

    /**
     * Returns a comma separated string of question marks ?,?,?,?
     *
     * @param array $values Must have at least 1 value
     * @return string
     */
    public function getMarks($values)
    {
        return '?' . str_repeat(',?', count($values) - 1);
    }

    protected function getMarkGroups(array $columns, $values)
    {
        $columnCount = count($columns);
        $template = "(?" . str_repeat(",?", $columnCount - 1) . ")";
        $rowCount = count($values) / $columnCount;
        $followingTemplate = ", " . $template;
        $valuesClause = $template . str_repeat($followingTemplate, ($rowCount - 1));

        return $valuesClause;
    }

    /**
     * Executes a grouped query in chunks, to avoid the max_allowed_packet constraint
     *
     * @param string $query
     * @param $columns
     * @param $values
     * @param $magnitude
     */
    protected function chunkedGroupExecute(string $query, $columns, $values, $magnitude)
    {
        // number of inserts per batch = max available kB per MySQL packet / size of single insert in kB
        $chunkSize = $this->maxAllowedPacket / $magnitude;

        foreach (array_chunk($values, $chunkSize * count($columns)) as $chunk) {
            $marks = $this->getMarkGroups($columns, $chunk);
            $plainQuery = str_replace('{{marks}}', $marks, $query);
            $this->execute($plainQuery, $chunk);
        }
    }

    /**
     * @return int
     */
    public function getLastInsertId()
    {
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Returns the first cell of the first result of $query.
     *
     * @param string $query
     * @param array $params
     * @return string|null
     */
    public function fetchSingleCell(string $query, array $params = [])
    {
        $column = $this->execute($query, $params)->fetchColumn(0);

        return $column === false ? null : $column;
    }

    /**
     * Returns an array containing the first cells of each result of $query.
     *
     * @param string $query
     * @param array $params
     * @return array
     */
    public function fetchSingleColumn(string $query, array $params = [])
    {
        return $this->execute($query, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Returns a key => value array based on the first two select fields of $query.
     *
     * @param string $query
     * @param array $params
     * @return array
     */
    public function fetchMap(string $query, array $params = [])
    {
        return $this->execute($query, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * @param string $query
     * @param array $params
     * @return array
     */
    public function fetchRow(string $query, array $params = [])
    {
        return $this->execute($query, $params)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $query
     * @param array $params
     * @return array
     */
    public function fetchAllAssoc(string $query, array $params = [])
    {
        return $this->execute($query, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $query
     * @param array $params
     * @param array $groupColumns
     * @return array
     */
    public function fetchGrouped(string $query, array $params, array $groupColumns)
    {
        $all = $this->execute($query, $params)->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($all as $row) {
            $path = &$grouped;
            foreach ($groupColumns as $groupColumn) {
                $key = $row[$groupColumn];
                if (!array_key_exists($key, $path)) {
                    $path[$key] = [];
                }
                $path = &$path[$key];
            }
            $path = $row;
        }

        return $grouped;
    }

    /**
     * @param string $query
     * @param array $params
     * @return array
     */
    public function fetchAllNonAssoc(string $query, array $params = [])
    {
        return $this->execute($query, $params)->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * Returns prefixed table name
     *
     * @param string $table
     * @return string
     */
    public function getFullTableName(string $table)
    {
        return $this->connection->getTableName($table);
    }
}
