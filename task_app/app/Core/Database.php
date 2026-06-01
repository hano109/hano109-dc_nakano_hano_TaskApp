<?php

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $configPath = dirname(__DIR__, 2) . '/config/database.php';
        if (!file_exists($configPath)) {
            throw new RuntimeException('config/database.php が見つかりません。');
        }

        $config = require $configPath;

        $driver = $config['driver'] ?? 'mysql';
        $host = $config['host'] ?? 'localhost';
        $dbname = $config['dbname'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [];

        $dsn = sprintf(
            '%s:host=%s;dbname=%s;charset=%s',
            $driver,
            $host,
            $dbname,
            $charset
        );

        self::$connection = new PDO($dsn, $username, $password, $options);

        return self::$connection;
    }
}
