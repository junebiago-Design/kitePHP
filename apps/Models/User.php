<?php

namespace Apps\Models;

use Core\Database;
use PDO;

class User
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database('UserTable.sqlite');

        $this->pdo = $database->connection();
    }

    /**
     * Get all users.
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM users ORDER BY id DESC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Get one user by ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $id
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Create a user.
     */
    public function create(
        string $username,
        string $email
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email)
             VALUES (:username, :email)'
        );

        $stmt->execute([
            ':username' => $username,
            ':email'    => $email
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update a user.
     */
    public function update(
        int $id,
        string $username,
        string $email
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET username = :username,
                 email = :email
             WHERE id = :id'
        );

        return $stmt->execute([
            ':id'       => $id,
            ':username' => $username,
            ':email'    => $email
        ]);
    }

    /**
     * Delete a user.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM users WHERE id = :id'
        );

        return $stmt->execute([
            ':id' => $id
        ]);
    }
}