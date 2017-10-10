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
    /** @var ResourceConnection $connection */
    private $connection;

    /** @var  PDO */
    private $pdo;

    /** @var  string Current time, in %Y-%m-%d %H:%M:%S */
    public $time;

    public function __construct(ResourceConnection $connection)
    {
        $this->connection = $connection;

        /** @var Mysql $mysql */
        $mysql = $connection->getConnection();

        /** @var PDO $pdo */
        $this->pdo = $mysql->getConnection();

        $this->time = strftime('%Y-%m-%d %H:%M:%S');
    }

    /**
     * Performs an insert query
     *
     * @param string $query
     */
    public function insert(string $query)
    {
#echo $query."\n";
        $this->pdo->query($query);
    }

    /**
     * Returns the first cell of the first result of $query.
     *
     * @param string $query
     * @return string|null
     */
    public function fetchSingleCell(string $query)
    {
        $column = $this->pdo->query($query)->fetchColumn(0);
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
        $map = [];
        foreach ($this->pdo->query($query)->fetchAll() as $row) {
            $map[] = $row[0];
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
#echo $query."\n";
        $map = [];
        foreach ($this->pdo->query($query)->fetchAll() as $row) {
            $map[$row[0]] = $row[1];
        }
        return $map;
    }

    /**
     * @param string $query
     * @return array
     */
    public function fetchAll(string $query)
    {
        return $this->pdo->query($query)->fetchAll();
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
        $esc = "";
        $sep = "";

        foreach ($set as $value) {
            $esc .= $sep . $this->pdo->quote($value);
            $sep = ",";
        }

        return $esc;
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