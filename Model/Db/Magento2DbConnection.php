<?php

namespace BigBridge\ProductImport\Model\Db;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use PDO;

/**
 * Wrapper class for the PDO object that Magento 2 uses.
 *
 * @author Patrick van Bergen
 */
class Magento2DbConnection
{
    const SLOW = 0.1;

    /** @var ResourceConnection $connection */
    protected $connection;

    /** @var  PDO */
    protected $pdo;

    protected $echoSlowQueries = false;

    public function __construct(ResourceConnection $connection)
    {
        $this->connection = $connection;

        /** @var Mysql $mysql */
        $mysql = $connection->getConnection();

        /** @var PDO $pdo */
        $this->pdo = $mysql->getConnection();

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Performs an insert query
     *
     * @param string $query
     */
    public function execute(string $query, $values = [])
    {
        $a = microtime(true);

#echo $query . "\n";
        $st = $this->pdo->prepare($query);
        $st->execute($values);

        if ($this->echoSlowQueries) {
            $b = microtime(true);
            if ($b - $a > self::SLOW) {
                echo ($b - $a) . ": " . substr($query, 0, 1000) . "\n";
            }
        }
    }

    protected function getMarks(array $columns, $values)
    {
        $columnCount = count($columns);
        $template = "(?" . str_repeat(", ?", $columnCount - 1) . ")";
        $rowCount = count($values) / $columnCount;
        $followingTemplate = ", " . $template;
        $valuesClause = $template . str_repeat($followingTemplate, ($rowCount - 1));

        return $valuesClause;
    }

    public function insertMultiple(string $table, array $columns, array $values)
    {
        if (empty($values)) {
            return;
        }

        $this->execute("
            INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) 
            VALUES " . $this->getMarks($columns, $values), $values);
    }

    public function insertMultipleWithUpdate(string $table, array $columns, array $values, string $updateClause)
    {
        if (empty($values)) {
            return;
        }

        $this->execute("
            INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) 
            VALUES " . $this->getMarks($columns, $values) . "
            ON DUPLICATE KEY UPDATE {$updateClause}",
            $values);
    }


    public function insertMultipleWithIgnore(string $table, array $columns, array $values)
    {
        if (empty($values)) {
            return;
        }

        $this->execute("
            INSERT IGNORE INTO `{$table}` (`" . implode('`, `', $columns) . "`) 
            VALUES " . $this->getMarks($columns, $values),
            $values);
    }

    public function deleteMultiple(string $table, string $keyColumn, array $keys)
    {
        if (empty($keys)) {
            return;
        }

        $this->execute("
            DELETE FROM`{$table}`  
            WHERE `{$keyColumn}` IN (?" . str_repeat(',?', count($keys) - 1) . ")",
            $keys);
    }

    public function deleteMultipleWithWhere(string $table, string $keyColumn, array $keys, string $whereClause)
    {
        if (empty($keys)) {
            return;
        }

        $this->execute("
            DELETE FROM`{$table}`  
            WHERE `{$keyColumn}` IN (?" . str_repeat(',?', count($keys) - 1) . ") AND {$whereClause}",
            $keys);
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
     * @return string|null
     */
    public function fetchSingleCell(string $query)
    {
        $a = microtime(true);

        $column = $this->pdo->query($query)->fetchColumn(0);

        if ($this->echoSlowQueries) {
            $b = microtime(true);
            if ($b - $a > self::SLOW) {
                echo ($b - $a) . ": " . substr($query, 0, 1000) . "\n";
            }
        }

        return $column === false ? null : $column;
    }

    /**
     * Returns an array containing the first cells of each result of $query.
     *
     * @param string $query
     * @return array
     */
    public function fetchSingleColumn(string $query)
    {
        $a = microtime(true);

        $map = [];
        foreach ($this->pdo->query($query)->fetchAll() as $row) {
            $map[] = $row[0];
        }

        if ($this->echoSlowQueries) {
            $b = microtime(true);
            if ($b - $a > self::SLOW) {
                echo ($b - $a) . ": " . substr($query, 0, 1000) . "\n";
            }
        }

        return $map;
    }

    /**
     * Returns a key => value array based on the first two select fields of $query.
     *
     * @param string $query
     * @return array
     */
    public function fetchMap(string $query)
    {
        $a = microtime(true);

        $map = [];
#echo $query . "\n";
        foreach ($this->pdo->query($query)->fetchAll() as $row) {
            $map[$row[0]] = $row[1];
        }

        if ($this->echoSlowQueries) {
            $b = microtime(true);
            if ($b - $a > self::SLOW) {
                echo ($b - $a) . ": " . substr($query, 0, 1000) . "\n";
            }
        }

        return $map;
    }

    /**
     * @param string $query
     * @return array
     */
    public function fetchRow(string $query)
    {
        $a = microtime(true);

        $row = $this->pdo->query($query)->fetch(PDO::FETCH_ASSOC);

        if ($this->echoSlowQueries) {
            $b = microtime(true);
            if ($b - $a > self::SLOW) {
                echo ($b - $a) . ": " . substr($query, 0, 1000) . "\n";
            }
        }

        return $row;
    }

    /**
     * @param string $query
     * @return array
     */
    public function fetchAllAssoc(string $query)
    {
        $a = microtime(true);
#echo $query . "\n";
        $result = $this->pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

        if ($this->echoSlowQueries) {
            $b = microtime(true);
            if ($b - $a > self::SLOW) {
                echo ($b - $a) . ": " . substr($query, 0, 1000) . "\n";
            }
        }

        return $result;
    }

    /**
     * @param string $query
     * @return array
     */
    public function fetchAllNonAssoc(string $query)
    {
#echo $query . "\n";
        $a = microtime(true);

        $result = $this->pdo->query($query)->fetchAll(PDO::FETCH_NUM);

        if ($this->echoSlowQueries) {
            $b = microtime(true);
            if ($b - $a > self::SLOW) {
                echo ($b - $a) . ": " . substr($query, 0, 1000) . "\n";
            }
        }

        return $result;
    }

    /**
     * Escapes $value for use in a query. Adds quotes.
     *
     * @param string $value
     * @return string
     */
    public function quote(string $value)
    {
        return $this->pdo->quote($value);
    }

    /**
     * Escapes a set of values for IN
     *
     * @param array $set
     * @return string
     */
    public function quoteSet(array $set)
    {
        $esc = [];

        foreach ($set as $value) {
            $esc[] = $this->pdo->quote($value);
        }

        return implode(",", $esc);
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