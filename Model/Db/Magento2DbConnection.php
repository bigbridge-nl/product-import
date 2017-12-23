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

    protected $logSlowQueries = false;

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
    public function execute(string $query)
    {
        $a = microtime(true);

#echo $query . "\n";
        $this->pdo->exec($query);

        if ($this->logSlowQueries) {
            $b = microtime(true);
            if ($b - $a > self::SLOW) {
                echo ($b - $a) . ": " . substr($query, 0, 1000) . "\n";
            }
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
     * @return string|null
     */
    public function fetchSingleCell(string $query)
    {
        $a = microtime(true);

        $column = $this->pdo->query($query)->fetchColumn(0);

        if ($this->logSlowQueries) {
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

        if ($this->logSlowQueries) {
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
        foreach ($this->pdo->query($query)->fetchAll() as $row) {
            $map[$row[0]] = $row[1];
        }

        if ($this->logSlowQueries) {
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

        if ($this->logSlowQueries) {
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

        $result = $this->pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

        if ($this->logSlowQueries) {
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
    public function fetchAllNumber(string $query)
    {
#echo $query . "\n";
        $a = microtime(true);

        $result = $this->pdo->query($query)->fetchAll(PDO::FETCH_NUM);

        if ($this->logSlowQueries) {
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