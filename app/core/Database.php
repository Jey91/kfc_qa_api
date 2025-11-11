<?php

namespace Core;

/**
 * Database connection and query handler
 */
class Database
{
    /**
     * @var \PDO PDO instance
     */
    private $pdo;

    /**
     * @var \PDOStatement Current PDO statement
     */
    private $statement;

    /**
     * @var array Database configuration
     */
    private $config;

    /**
     * @var Database Singleton instance
     */
    private static $instance = null;

    /**
     * @var int Number of executed queries
     */
    private $queryCount = 0;

    /**
     * @var array Query log
     */
    private $queryLog = [];

    /**
     * @var bool Whether to log queries
     */
    private $logQueries = false;

    /**
     * Get singleton instance
     * 
     * @param array $config Database configuration (optional)
     * @return Database Database instance
     */
    public static function getInstance(?array $config = null)
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        } elseif ($config !== null) {
            // Reconfigure the existing instance
            self::$instance->configure($config);
        }

        return self::$instance;
    }
    
    public static function reset(): void
    {
        if (self::$instance !== null) {
            self::$instance->close();   // you already have close()
            self::$instance = null;
        }
    }

    /** Reload using the current config file's 'default' connection */
    public static function reloadDefault(): void
    {
        self::reset();
        self::getInstance();  // this will call loadDefaultConfig() then connect()
    }

    /** Select a connection by name now (without editing the file) */

    public static function selectConnection(string $name): void
    {
        $configFile = dirname(__DIR__) . '/config/database.php';
        $cfg = require $configFile;

        if (!isset($cfg['connections'][$name])) {
            throw new \InvalidArgumentException("Unknown DB connection: {$name}");
        }

        $newConfig = $cfg['connections'][$name];

        if (self::$instance === null) {
            // First use: create with target config
            self::$instance = new self($newConfig);
        } else {
            // IMPORTANT: keep the same object; reconfigure + reconnect
            self::$instance->configure($newConfig);
        }
    }


    /**
     * Constructor
     * 
     * @param array $config Database configuration
     */
    private function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->configure($config);
        } else {
            // Load default configuration
            $this->loadDefaultConfig();
        }

        // Connect to database
        $this->connect();
    }

    /**
     * Configure database connection
     * 
     * @param array $config Database configuration
     * @return $this For method chaining
     */
    public function configure(array $config)
    {
        $this->config = $config;

        // 切库前把旧的 statement 置空，避免用旧连接的句柄
        $this->statement = null;

        // 无论是否已有 PDO，都直接重连；确保换库成功
        $this->connect();
        return $this;
    }

    /**
     * Load default configuration from environment variables or config file
     */
    private function loadDefaultConfig()
    {
        // Initialize config as empty array
        $this->config = [];

        // Try to load from config file
        $configFile = dirname(__DIR__) . '/config/database.php';

        if (file_exists($configFile)) {
            $fileConfig = require $configFile;

            if (is_array($fileConfig)) {
                // Get the default connection type
                $defaultConnection = $fileConfig['default'] ?? 'mysql';

                // Get the connection configuration for the default type
                if (
                    isset($fileConfig['connections'][$defaultConnection]) &&
                    is_array($fileConfig['connections'][$defaultConnection])
                ) {
                    $this->config = $fileConfig['connections'][$defaultConnection];

                    // Add log_queries setting if present
                    if (isset($fileConfig['log_queries'])) {
                        $this->logQueries = (bool) $fileConfig['log_queries'];
                    }
                }
            }
        }
    }

    /**
     * Connect to the database
     * 
     * @throws \PDOException If connection fails
     */
    private function connect()
    {
        $this->statement = null;
        // Build DSN
        $dsn = $this->buildDsn();

        try {
            // Create PDO instance
            $this->pdo = new \PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options'] ?? []
            );

            // Reset query count and log
            $this->queryCount = 0;
            $this->queryLog = [];
        } catch (\PDOException $e) {
            // Mask credentials in error message
            $message = str_replace(
                $this->config['password'],
                '****',
                $e->getMessage()
            );

            throw new \PDOException('Database connection failed: ' . $message, $e->getCode());
        }
    }

    /**
     * Build DSN string based on configuration
     * 
     * @return string DSN string
     */
    private function buildDsn()
    {
        $driver = $this->config['driver'] ?? 'mysql';

        switch ($driver) {
            case 'mysql':
                return sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $this->config['host'],
                    $this->config['port'] ?? '3306',
                    $this->config['database'],
                    $this->config['charset'] ?? 'utf8mb4'
                );

            case 'pgsql':
                return sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $this->config['host'],
                    $this->config['port'] ?? '5432',
                    $this->config['database']
                );

            case 'sqlite':
                return 'sqlite:' . $this->config['database'];

            case 'sqlsrv':
                $dsn = sprintf(
                    'sqlsrv:Server=%s,%s;Database=%s',
                    $this->config['host'],
                    $this->config['port'] ?? '1433',
                    $this->config['database']
                );
                // Add TrustServerCertificate for SQL Server to bypass self-signed certificate errors
                if (isset($this->config['TrustServerCertificate']) && $this->config['TrustServerCertificate']) {
                    $dsn .= ';TrustServerCertificate=yes';
                }
                return $dsn;

            default:
                throw new \InvalidArgumentException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Prepare a SQL statement
     * 
     * @param string $sql SQL query
     * @return $this For method chaining
     */
    public function prepare($sql)
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        $this->statement = $this->pdo->prepare($sql);
        return $this;
    }

    /**
     * Bind a value to a parameter
     * 
     * @param mixed $param Parameter identifier
     * @param mixed $value Value to bind
     * @param int $type Parameter type
     * @return $this For method chaining
     */
    public function bind($param, $value, $type = null)
    {
        if ($type === null) {
            switch (true) {
                case is_int($value):
                    $type = \PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = \PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = \PDO::PARAM_STR;
                    $value = ''; // Convert null to empty string for string types
                    break;
                default:
                    $type = \PDO::PARAM_STR;
            }
        } else if (is_null($value) && ($type === \PDO::PARAM_STR)) {
            // If explicitly set as string type and value is null, use empty string
            $value = '';
        }

        $this->statement->bindValue($param, $value, $type);
        return $this;
    }

    /**
     * Bind multiple values to parameters
     * 
     * @param array $params Parameters and values to bind
     * @return $this For method chaining
     */
    public function bindParams(array $params)
    {
        foreach ($params as $param => $value) {
            $this->bind($param, $value);
        }

        return $this;
    }

    /**
     * Execute the prepared statement
     * 
     * @param array $params Parameters to bind (optional)
     * @return bool Success status
     */
    public function execute(array $params = [])
    {
        $startTime = microtime(true);

        if (!empty($params)) {
            $result = $this->statement->execute($params);
        } else {
            $result = $this->statement->execute();
        }

        $this->queryCount++;

        if ($this->logQueries) {
            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $this->queryLog[] = [
                'query' => $this->statement->queryString,
                'params' => $params,
                'duration' => $duration,
                'time' => date('Y-m-d H:i:s')
            ];
        }

        return $result;
    }

    /**
     * Execute a SQL query directly and return the Database instance for method chaining
     * 
     * @param string $sql SQL query
     * @param array $params Parameters to bind (optional)
     * @return $this Database instance for method chaining
     */
    public function query($sql, array $params = [])
    {
        $this->prepare($sql);
        $this->execute($params);
        return $this;
    }

    /**
     * Fetch a single row with null string handling
     * 
     * @param int $fetchMode Fetch mode
     * @return mixed Result row or false
     */
    public function fetch($fetchMode = null)
    {
        $result = ($fetchMode !== null)
            ? $this->statement->fetch($fetchMode)
            : $this->statement->fetch();

        if ($result) {
            $result = $this->handleNullStrings($result);
        }

        return $result;
    }

    /**
     * Fetch all rows with null string handling
     * 
     * @param int $fetchMode Fetch mode
     * @return array Result rows
     */
    public function fetchAll($fetchMode = null)
    {
        $results = ($fetchMode !== null)
            ? $this->statement->fetchAll($fetchMode)
            : $this->statement->fetchAll();

        foreach ($results as &$row) {
            $row = $this->handleNullStrings($row);
        }

        return $results;
    }

    /**
     * Convert null values to empty strings for string columns
     * 
     * @param array|object $row Database row
     * @return array|object Processed row
     */
    private function handleNullStrings($row)
    {
        if (is_object($row)) {
            foreach (get_object_vars($row) as $key => $value) {
                if ($value === null) {
                    $row->$key = '';
                }
            }
        } else if (is_array($row)) {
            foreach ($row as $key => $value) {
                if ($value === null) {
                    $row[$key] = '';
                }
            }
        }

        return $row;
    }

    /**
     * Fetch a single column
     * 
     * @param int $columnNumber Column number (0-indexed)
     * @return mixed Column value or false
     */
    public function fetchColumn($columnNumber = 0)
    {
        $value = $this->statement->fetchColumn($columnNumber);
        return $value === null ? '' : $value;
    }

    /**
     * Get the number of affected rows
     * 
     * @return int Number of rows
     */
    public function rowCount()
    {
        return $this->statement->rowCount();
    }

    /**
     * Get the last inserted ID
     * 
     * @param string $name Name of the sequence object (optional)
     * @return string Last inserted ID
     */
    public function lastInsertId($name = null)
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * Begin a transaction
     * 
     * @return bool Success status
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     * 
     * @return bool Success status
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * Roll back a transaction
     * 
     * @return bool Success status
     */
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if in a transaction
     * 
     * @return bool Whether in a transaction
     */
    public function inTransaction()
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Enable or disable query logging
     * 
     * @param bool $enable Whether to enable logging
     * @return $this For method chaining
     */
    public function enableQueryLog($enable = true)
    {
        $this->logQueries = $enable;

        if (!$enable) {
            $this->queryLog = [];
        }

        return $this;
    }

    /**
     * Get the query log
     * 
     * @return array Query log
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Get the number of executed queries
     * 
     * @return int Query count
     */
    public function getQueryCount()
    {
        return $this->queryCount;
    }

    /**
     * Get the PDO instance
     * 
     * @return \PDO PDO instance
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Get the current PDO statement
     * 
     * @return \PDOStatement PDO statement
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * Execute a callback in a transaction
     * 
     * @param callable $callback Callback to execute
     * @return mixed Result of the callback
     * @throws \Throwable If callback throws an exception
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Insert a record into a table
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @return int|string Last insert ID
     */
    public function insert($table, array $data)
    {
        // Build column and placeholder lists
        $columns = array_keys($data);
        $placeholders = array_map(function ($column) {
            return ':' . $column;
        }, $columns);

        // Build SQL query
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        // Execute query
        $this->prepare($sql);
        $this->bindParams($this->prepareBindings($data));
        $this->execute();

        return $this->lastInsertId();
    }

    /**
     * Update records in a table
     * 
     * @param string $table Table name
     * @param array $data Data to update
     * @param string $where Where clause
     * @param array $whereParams Where clause parameters
     * @return int Number of affected rows
     */
    public function update($table, array $data, $where, array $whereParams = [])
    {
        // Build set clause
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = $column . ' = :' . $column;
        }

        // Build SQL query
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $set),
            $where
        );

        // Merge data and where parameters
        $params = array_merge($this->prepareBindings($data), $whereParams);

        // Execute query
        $this->prepare($sql);
        $this->bindParams($params);
        $this->execute();

        return $this->rowCount();
    }

    /**
     * Delete records from a table
     * 
     * @param string $table Table name
     * @param string $where Where clause
     * @param array $params Where clause parameters
     * @return int Number of affected rows
     */
    public function delete($table, $where, array $params = [])
    {
        // Build SQL query
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);

        // Execute query
        $this->prepare($sql);
        $this->bindParams($params);
        $this->execute();

        return $this->rowCount();
    }

    /**
     * Select records from a table
     * 
     * @param string $table Table name
     * @param array $columns Columns to select
     * @param string $where Where clause (optional)
     * @param array $params Where clause parameters (optional)
     * @param string $orderBy Order by clause (optional)
     * @param int $limit Limit (optional)
     * @param int $offset Offset (optional)
     * @return array Result rows
     */
    public function select($table, array $columns = ['*'], $where = '', array $params = [], $orderBy = '', $limit = null, $offset = null)
    {
        // Build SQL query
        $sql = sprintf('SELECT %s FROM %s', implode(', ', $columns), $table);

        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
        }

        if (!empty($orderBy)) {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;

            if ($offset !== null) {
                $sql .= ' OFFSET ' . (int) $offset;
            }
        }

        // Execute query
        $this->prepare($sql);

        if (!empty($params)) {
            $this->bindParams($params);
        }

        $this->execute();

        return $this->fetchAll();
    }

    /**
     * Count records in a table
     * 
     * @param string $table Table name
     * @param string $where Where clause (optional)
     * @param array $params Where clause parameters (optional)
     * @return int Record count
     */
    public function count($table, $where = '', array $params = [])
    {
        // Build SQL query
        $sql = sprintf('SELECT COUNT(*) FROM %s', $table);

        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
        }

        // Execute query
        $this->prepare($sql);

        if (!empty($params)) {
            $this->bindParams($params);
        }

        $this->execute();

        return (int) $this->fetchColumn(0);
    }

    /**
     * Check if a record exists
     * 
     * @param string $table Table name
     * @param string $where Where clause
     * @param array $params Where clause parameters (optional)
     * @return bool Whether record exists
     */
    public function exists($table, $where, array $params = [])
    {
        return $this->count($table, $where, $params) > 0;
    }

    /**
     * Get a single record by ID
     * 
     * @param string $table Table name
     * @param mixed $id Record ID
     * @param string $idColumn ID column name (optional)
     * @return array|false Record or false if not found
     */
    public function find($table, $id, $idColumn = 'id')
    {
        // Build SQL query
        $sql = sprintf('SELECT * FROM %s WHERE %s = :id LIMIT 1', $table, $idColumn);

        // Execute query
        $this->prepare($sql);
        $this->bind(':id', $id);
        $this->execute();

        return $this->fetch();
    }

    /**
     * Prepare bindings for PDO
     * 
     * @param array $bindings Bindings to prepare
     * @return array Prepared bindings
     */
    private function prepareBindings(array $bindings)
    {
        $result = [];

        foreach ($bindings as $key => $value) {
            $result[':' . $key] = $value;
        }

        return $result;
    }

    /**
     * Execute raw SQL
     * 
     * @param string $sql SQL query
     * @return bool Success status
     */
    public function raw($sql)
    {
        return $this->pdo->exec($sql) !== false;
    }

    /**
     * Close the database connection
     */
    public function close()
    {
        $this->pdo = null;
        $this->statement = null;
    }

    /**
     * Prevent cloning of singleton instance
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing of singleton instance
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}