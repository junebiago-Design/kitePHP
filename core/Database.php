<?php

namespace Core;

use PDO;

class Database
{
    protected PDO $pdo;

    public function __construct(string $database)
    {
        $dbFile = dirname(__DIR__) . '/database/' . $database;

        $this->pdo = new PDO(
            'sqlite:' . $dbFile,
            null,
            null,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    public function connection(): PDO
    {
        return $this->pdo;
    }
}