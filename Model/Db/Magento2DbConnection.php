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
        $this->pdo->query($query);
    }

    public function quote(string $query)
    {
        return $this->pdo->quote($query);
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