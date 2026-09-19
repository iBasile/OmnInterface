<?php

/** Connexion PDO et création automatique du schéma au premier accès. */
class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo !== null) return self::$pdo;
        if (DB_DRIVER === 'sqlite') {
            if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH), 0770, true);
            $isNew = !file_exists(DB_PATH);
            self::$pdo = new PDO('sqlite:' . DB_PATH);
        } elseif (DB_DRIVER === 'mysql') {
            $isNew = true;
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            self::$pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
        } else {
            throw new RuntimeException('DB_DRIVER doit être "sqlite" ou "mysql".');
        }
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if (DB_DRIVER === 'sqlite') self::$pdo->exec('PRAGMA foreign_keys = ON');
        if ($isNew) self::migrate();
        else self::ensureSchema();
        self::ensureApiKeyColumn();
        return self::$pdo;
    }

    private static function migrate(): void
    {
        $schema = DB_DRIVER === 'mysql' ? 'schema.mysql.sql' : 'schema.sql';
        self::$pdo->exec((string) file_get_contents(__DIR__ . '/' . $schema));
    }

    private static function ensureSchema(): void
    {
        self::migrate();
    }

    private static function ensureApiKeyColumn(): void
    {
        $columns = DB_DRIVER === 'mysql'
            ? self::$pdo->query('SHOW COLUMNS FROM users')->fetchAll()
            : self::$pdo->query('PRAGMA table_info(users)')->fetchAll();
        foreach ($columns as $column) {
            if (($column['Field'] ?? $column['name'] ?? '') === 'omniroute_api_key') return;
        }
        self::$pdo->exec('ALTER TABLE users ADD COLUMN omniroute_api_key TEXT NULL');
    }
}
