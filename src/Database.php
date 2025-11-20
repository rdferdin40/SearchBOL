<?php
/**
 * Database Connection and Query Handler
 */

namespace BOLSearch;

use PDO;
use PDOException;
use Exception;

class Database
{
    private static ?PDO $instance = null;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get singleton PDO instance
     */
    public static function getInstance(array $config = null): PDO
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new Exception('Database configuration required for first initialization');
            }

            $db = new self($config);
            self::$instance = $db->connect();
        }

        return self::$instance;
    }

    /**
     * Create PDO connection
     */
    private function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];

        try {
            return new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute a query with parameters
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Fetch single row
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Execute insert and return last insert ID
     */
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int)self::getInstance()->lastInsertId();
    }

    /**
     * Execute update/delete and return affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }

    /**
     * Check if in transaction
     */
    public static function inTransaction(): bool
    {
        return self::getInstance()->inTransaction();
    }
}
