<?php

namespace BigBridge\ProductImport\Model\Db;

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
     * Prepares and executes an SQL query or statement
     *
     * @param string $query
     * @param array $values
     * @return \PDOStatement
     */
    public function execute(string $query, $values = [])
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

    public function getMarks($values)
    {
        return '?' . str_repeat(',?', count($values) - 1);
    }

    protected function getMarkGroups(array $columns, $values)
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
            VALUES " . $this->getMarkGroups($columns, $values), $values);
    }

    public function insertMultipleWithUpdate(string $table, array $columns, array $values, string $updateClause)
    {
        if (empty($values)) {
            return;
        }

        $this->execute("
            INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) 
            VALUES " . $this->getMarkGroups($columns, $values) . "
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
            VALUES " . $this->getMarkGroups($columns, $values),
            $values);
    }

    public function deleteMultiple(string $table, string $keyColumn, array $keys)
    {
        if (empty($keys)) {
            return;
        }

        $this->execute("
            DELETE FROM`{$table}`  
            WHERE `{$keyColumn}` IN (" . $this->getMarks($keys)  . ")",
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