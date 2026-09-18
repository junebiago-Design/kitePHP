<?php

namespace Apps\Models;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseModel
{
    protected string $databaseDir;
    protected ?PDO $pdo = null;
    protected string $currentDbName = '';
    protected string $currentDbFile = '';

    public function __construct(string $databaseDir = '')
    {
        if ($databaseDir === '') {
            $databaseDir = dirname(__DIR__, 2) . '/database';
        }
        $this->databaseDir = rtrim($databaseDir, '/\\');

        if (!is_dir($this->databaseDir)) {
            if (!mkdir($this->databaseDir, 0755, true) && !is_dir($this->databaseDir)) {
                throw new RuntimeException("Cannot create database directory: {$this->databaseDir}");
            }
        }
    }

    // ---------------------------------------------------------
    // Connection
    // ---------------------------------------------------------

    public function connect(string $name): self
    {
        $name = $this->sanitizeName($name);

        if ($this->pdo && $this->currentDbName === $name) {
            return $this;
        }

        $this->currentDbName = $name;
        $this->currentDbFile = $this->databaseDir . '/' . $name . '.sqlite';

        try {
            $this->pdo = new PDO('sqlite:' . $this->currentDbFile);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            throw new RuntimeException("Cannot open database '{$name}': " . $e->getMessage());
        }

        return $this;
    }

    protected function requireConnection(): PDO
    {
        if (!$this->pdo) {
            throw new RuntimeException('No database selected. Call connect() first.');
        }
        return $this->pdo;
    }

    // ---------------------------------------------------------
    // Databases
    // ---------------------------------------------------------

    public static function listDatabases(string $dir = ''): array
    {
        if ($dir === '') {
            $dir = dirname(__DIR__, 2) . '/database';
        }
        if (!is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (glob($dir . '/*.sqlite') ?: [] as $file) {
            $name = basename($file, '.sqlite');
            $out[] = [
                'name'        => $name,
                'description' => '',
                'tables'      => self::listTablesStatic($file),
            ];
        }
        return $out;
    }

    public function createDatabase(string $name, string $description = ''): void
    {
        $name = $this->sanitizeName($name);
        $file = $this->databaseDir . '/' . $name . '.sqlite';

        if (file_exists($file)) {
            throw new RuntimeException("Database '{$name}' already exists.");
        }

        $this->connect($name);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS __meta (key TEXT PRIMARY KEY, value TEXT)');
        if ($description !== '') {
            $this->pdo->prepare('INSERT OR REPLACE INTO __meta (key, value) VALUES (?, ?)')
                ->execute(['description', $description]);
        }
    }

    public function deleteDatabase(): void
    {
        $this->pdo = null;
        if ($this->currentDbFile !== '' && file_exists($this->currentDbFile)) {
            if (!@unlink($this->currentDbFile)) {
                throw new RuntimeException("Cannot delete {$this->currentDbFile}");
            }
        }
    }

    // ---------------------------------------------------------
    // Tables
    // ---------------------------------------------------------

    public function listTables(): array
    {
        $pdo = $this->requireConnection();
        $stmt = $pdo->query(
            "SELECT name FROM sqlite_master
             WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != '__meta'
             ORDER BY name"
        );
        return array_map(
            fn($n) => ['name' => $n, 'description' => ''],
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    protected static function listTablesStatic(string $file): array
    {
        try {
            $pdo = new PDO('sqlite:' . $file);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->query(
                "SELECT name FROM sqlite_master
                 WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != '__meta'
                 ORDER BY name"
            );
            return array_map(
                fn($n) => ['name' => $n, 'description' => ''],
                $stmt->fetchAll(PDO::FETCH_COLUMN)
            );
        } catch (PDOException $e) {
            return [];
        }
    }

    public function createTable(string $tableName, array $columns, string $description = ''): void
    {
        $pdo = $this->requireConnection();
        $tableName = $this->sanitizeIdentifier($tableName);

        if (empty($columns)) {
            throw new RuntimeException('At least one column is required.');
        }

        $defs   = [];
        $pkCols = [];
        $fkDefs = [];

        foreach ($columns as $col) {
            $colName = $this->sanitizeIdentifier($col['name'] ?? '');
            $type    = strtoupper($col['type'] ?? 'TEXT');
            if ($type === 'PASSWORD') $type = 'TEXT';

            $sqlType = $type;
            if (!empty($col['length']) && in_array($type, ['VARCHAR','CHAR','DECIMAL','NUMERIC'], true)) {
                $sqlType .= '(' . (int)$col['length'] . ')';
            }

            $def = "`{$colName}` {$sqlType}";

            if (!empty($col['pk'])) {
                $pkCols[] = $colName;
                if (!empty($col['autoincrement']) && $type === 'INTEGER') {
                    $def .= ' PRIMARY KEY AUTOINCREMENT';
                }
            }

            $defs[] = $def;

            if (!empty($col['fk_table']) && !empty($col['fk_column'])) {
                $fkTable  = $this->sanitizeIdentifier($col['fk_table']);
                $fkColumn = $this->sanitizeIdentifier($col['fk_column']);
                $fkDefs[] = "FOREIGN KEY (`{$colName}`) REFERENCES `{$fkTable}`(`{$fkColumn}`)";
            }
        }

        if (!empty($pkCols) && !$this->hasAutoIncrement($columns)) {
            $defs[] = 'PRIMARY KEY (' . implode(', ', array_map(fn($c) => "`{$c}`", $pkCols)) . ')';
        }

        $sql = "CREATE TABLE `{$tableName}` (\n  " . implode(",\n  ", array_merge($defs, $fkDefs)) . "\n)";
        $pdo->exec($sql);

        if ($description !== '') {
            $pdo->prepare('INSERT OR REPLACE INTO __meta (key, value) VALUES (?, ?)')
                ->execute(["table:{$tableName}:description", $description]);
        }
    }

    protected function hasAutoIncrement(array $columns): bool
    {
        foreach ($columns as $c) {
            if (!empty($c['pk']) && !empty($c['autoincrement']) && strtoupper($c['type'] ?? '') === 'INTEGER') {
                return true;
            }
        }
        return false;
    }

    public function deleteTable(string $tableName): void
    {
        $pdo = $this->requireConnection();
        $pdo->exec("DROP TABLE IF EXISTS `" . $this->sanitizeIdentifier($tableName) . "`");
    }

    public function getTableSchema(string $tableName): array
    {
        $pdo = $this->requireConnection();
        $tableName = $this->sanitizeIdentifier($tableName);

        $columns = [];
        foreach ($pdo->query("PRAGMA table_info(`{$tableName}`)")->fetchAll() as $row) {
            $columns[] = [
                'name'          => $row['name'],
                'type'          => $row['type'],
                'pk'            => (bool)$row['pk'],
                'autoincrement' => false,
                'notnull'       => (bool)$row['notnull'],
                'default'       => $row['dflt_value'],
            ];
        }

        $fk = $pdo->query("PRAGMA foreign_key_list(`{$tableName}`)")->fetchAll();

        return ['columns' => $columns, 'foreign_keys' => $fk];
    }

    // ---------------------------------------------------------
    // Rows
    // ---------------------------------------------------------

    public function selectRows(string $tableName, int $limit = 50, int $offset = 0): array
    {
        $pdo = $this->requireConnection();
        $tableName = $this->sanitizeIdentifier($tableName);
        $limit  = max(1, min($limit, 500));
        $offset = max(0, $offset);

        return $pdo->query("SELECT * FROM `{$tableName}` LIMIT {$limit} OFFSET {$offset}")->fetchAll();
    }

    public function insertRow(string $tableName, array $data): int
    {
        $pdo = $this->requireConnection();
        $tableName = $this->sanitizeIdentifier($tableName);

        if (empty($data)) throw new RuntimeException('No data provided.');

        $schema = $this->getTableSchema($tableName);
        $pwdCols = [];
        foreach ($schema['columns'] as $col) {
            if (stripos($col['type'], 'PASSWORD') === 0) $pwdCols[] = $col['name'];
        }
        foreach ($data as $k => $v) {
            if (in_array($k, $pwdCols, true) && $v !== '') {
                $data[$k] = password_hash($v, PASSWORD_DEFAULT);
            }
        }

        $cols    = array_keys($data);
        $holders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO `{$tableName}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $holders) . ")";

        $stmt = $pdo->prepare($sql);
        foreach ($data as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->execute();

        return (int)$pdo->lastInsertId();
    }

    public function updateRow(string $tableName, array $data, array $where): int
    {
        $pdo = $this->requireConnection();
        $tableName = $this->sanitizeIdentifier($tableName);

        if (empty($data) || empty($where)) throw new RuntimeException('Data and WHERE required.');

        $set = []; $bind = [];
        foreach ($data as $k => $v)  { $set[]  = "`{$k}` = :set_{$k}";   $bind[":set_{$k}"]   = $v; }
        $cond = [];
        foreach ($where as $k => $v) { $cond[] = "`{$k}` = :where_{$k}"; $bind[":where_{$k}"] = $v; }

        $stmt = $pdo->prepare("UPDATE `{$tableName}` SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $cond));
        $stmt->execute($bind);

        return $stmt->rowCount();
    }

    public function deleteRow(string $tableName, array $where): int
    {
        $pdo = $this->requireConnection();
        $tableName = $this->sanitizeIdentifier($tableName);

        if (empty($where)) throw new RuntimeException('WHERE required.');

        $cond = []; $bind = [];
        foreach ($where as $k => $v) { $cond[] = "`{$k}` = :w_{$k}"; $bind[":w_{$k}"] = $v; }

        $stmt = $pdo->prepare("DELETE FROM `{$tableName}` WHERE " . implode(' AND ', $cond));
        $stmt->execute($bind);

        return $stmt->rowCount();
    }

    // ---------------------------------------------------------
    // Raw SQL
    // ---------------------------------------------------------

    public function executeSql(string $sql): array
    {
        $pdo = $this->requireConnection();
        $statements = $this->splitSql($sql);
        if (empty($statements)) throw new RuntimeException('No SQL statements provided.');

        $results = [];
        $pdo->beginTransaction();
        try {
            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if ($trimmed === '') continue;

                $isSelect = (bool)preg_match('/^\s*(SELECT|PRAGMA|EXPLAIN)\b/i', $trimmed);
                $stmt = $pdo->query($trimmed);

                if ($isSelect) {
                    $rows = $stmt->fetchAll();
                    $results[] = ['sql' => $trimmed, 'type' => 'select', 'rows' => $rows, 'count' => count($rows)];
                } else {
                    $results[] = ['sql' => $trimmed, 'type' => 'write', 'affected' => $stmt->rowCount()];
                }
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new RuntimeException('SQL error: ' . $e->getMessage());
        }

        return $results;
    }

    protected function splitSql(string $sql): array
    {
        $statements = []; $current = ''; $inString = false; $stringChar = ''; $inComment = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch   = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inComment) { if ($ch === "\n") { $inComment = false; $current .= $ch; } continue; }
            if (!$inString && $ch === '-' && $next === '-') { $inComment = true; $i++; continue; }
            if (($ch === "'" || $ch === '"') && !$inString) { $inString = true; $stringChar = $ch; $current .= $ch; continue; }
            if ($inString) { $current .= $ch; if ($ch === $stringChar && $sql[$i - 1] !== '\\') $inString = false; continue; }
            if ($ch === ';') { if (trim($current) !== '') $statements[] = $current; $current = ''; continue; }
            $current .= $ch;
        }
        if (trim($current) !== '') $statements[] = $current;
        return $statements;
    }

    // ---------------------------------------------------------
    // Code generation  ← what the codegen view calls
    // ---------------------------------------------------------

    public function generateConnectionCode(): string
    {
        $name = $this->currentDbName;
        if ($name === '') throw new RuntimeException('No database connected.');

        $tables = $this->listTables();
        $relPath = 'database/' . $name . '.sqlite';

        $tableBlocks = '';
        foreach ($tables as $t) {
            $tName  = $t['name'];
            $schema = $this->getTableSchema($tName);

            $colList = implode(', ', array_map(fn($c) => $c['name'], $schema['columns']));
            $pkCols  = array_values(array_filter(
                $schema['columns'],
                fn($c) => !empty($c['pk'])
            ));

            // Pick a sensible WHERE clause for update/delete examples
            $whereKey  = $pkCols[0]['name']  ?? ($schema['columns'][0]['name'] ?? 'id');
            $whereType = $pkCols[0]['type']  ?? ($schema['columns'][0]['type'] ?? 'TEXT');

            $tableBlocks .= <<<PHP

// ─────────────────────────────────────────────
// Table: {$tName}  ({$colList})
// ─────────────────────────────────────────────
// Read all rows
\$rows = \$pdo->query("SELECT * FROM `{$tName}`")->fetchAll();

// Read one row by {$whereKey}
\$stmt = \$pdo->prepare("SELECT * FROM `{$tName}` WHERE `{$whereKey}` = :id");
\$stmt->execute([':id' => 1]);
\$row = \$stmt->fetch();

// Insert
\$stmt = \$pdo->prepare("INSERT INTO `{$tName}` (...) VALUES (...)");
// \$stmt->execute([...]);

// Update by {$whereKey}
\$stmt = \$pdo->prepare("UPDATE `{$tName}` SET ... WHERE `{$whereKey}` = :id");
// \$stmt->execute([... , ':id' => 1]);

// Delete by {$whereKey}
\$stmt = \$pdo->prepare("DELETE FROM `{$tName}` WHERE `{$whereKey}` = :id");
// \$stmt->execute([':id' => 1]);

PHP;
        }

        if ($tableBlocks === '') {
            $tableBlocks = "// (No tables in this database yet.)\n";
        }

        return <<<PHP
<?php
/**
 * Connection snippet for SQLite database '{$name}'
 * Auto-generated by KitePHP DatabaseManager.
 *
 * File on disk: {$relPath}
 */

declare(strict_types=1);

\$dbFile = __DIR__ . '/{$relPath}';

// Create the file if it doesn't exist yet (optional)
// if (!file_exists(\$dbFile)) { touch(\$dbFile); }

\$pdo = new PDO('sqlite:' . \$dbFile, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
\$pdo->exec('PRAGMA foreign_keys = ON');

// ─────────────────────────────────────────────
// Tables in this database: {$this->tableListForComment($tables)}
// ─────────────────────────────────────────────
{$tableBlocks}

// Done. Use \$pdo anywhere else in your project.
PHP;
    }

    protected function tableListForComment(array $tables): string
    {
        if (empty($tables)) return '(none)';
        return implode(', ', array_map(fn($t) => $t['name'], $tables));
    }

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------

    protected function sanitizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
            throw new RuntimeException("Invalid database name: '{$name}'");
        }
        return $name;
    }

    protected function sanitizeIdentifier(string $name): string
    {
        $name = trim($name);
        if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new RuntimeException("Invalid identifier: '{$name}'");
        }
        return $name;
    }
}