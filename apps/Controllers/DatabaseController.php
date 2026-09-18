<?php

namespace Apps\Controllers;

use Core\Controller;
use Apps\Models\DatabaseModel;

class DatabaseController extends Controller
{
    protected DatabaseModel $db;

    public function __construct(string $baseUrl = '')
    {
        parent::__construct($baseUrl);
        $this->db = new DatabaseModel(
            $this->projectRoot . '/database'
        );
    }

    /**
     * GET /database  →  full DB manager UI
     */
    public function index(): string
    {
        return $this->view('database', [
            'projectName' => 'Demo',
            'namespace'   => 'Apps\Controllers',
            'viewName'    => 'database',
            'ajaxUrl'     => $this->baseUrl . '/database/ajax',
        ]);
    }

    /**
     * GET /database/codegen  →  connection-code generator UI
     */
    public function codegen(): string
    {
        return $this->view('dbcodegenerator', [
            'projectName' => 'Demo',
            'namespace'   => 'Apps\Controllers',
            'viewName'    => 'dbcodegenerator',
            'ajaxUrl'     => $this->baseUrl . '/database/ajax',
        ]);
    }

    /**
     * POST /database/ajax  →  shared JSON API
     */
    public function ajax(): string
    {
        header('Content-Type: application/json');

        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        try {
            switch ($action) {

                // ---------- Databases ----------
                case 'list_databases':
                    $this->json([
                        'success' => true,
                        'data'    => DatabaseModel::listDatabases(
                            $this->projectRoot . '/database'
                        ),
                    ]);
                    break;

                case 'create_database':
                    $name = trim($_POST['db_name'] ?? '');
                    $desc = trim($_POST['description'] ?? '');
                    if ($name === '') throw new \RuntimeException('Database name is required.');
                    $this->db->createDatabase($name, $desc);
                    $this->json(['success' => true]);
                    break;

                case 'delete_database':
                    $this->db->connect($_POST['db_name'] ?? '')->deleteDatabase();
                    $this->json(['success' => true]);
                    break;

                // ---------- Tables ----------
                case 'create_table':
                    $columns = json_decode($_POST['columns'] ?? '[]', true);
                    if (!is_array($columns) || empty($columns)) {
                        throw new \RuntimeException('At least one column is required.');
                    }
                    $this->db
                        ->connect($_POST['db_name'] ?? '')
                        ->createTable(
                            $_POST['table_name'] ?? '',
                            $columns,
                            $_POST['description'] ?? ''
                        );
                    $this->json(['success' => true]);
                    break;

                case 'list_tables':
                    $this->json([
                        'success' => true,
                        'data'    => $this->db->connect($_POST['db_name'] ?? '')->listTables(),
                    ]);
                    break;

                case 'get_table_schema':
                    $this->json([
                        'success' => true,
                        'data'    => $this->db
                            ->connect($_POST['db_name'] ?? '')
                            ->getTableSchema($_POST['table_name'] ?? ''),
                    ]);
                    break;

                case 'delete_table':
                    $this->db
                        ->connect($_POST['db_name'] ?? '')
                        ->deleteTable($_POST['table_name'] ?? '');
                    $this->json(['success' => true]);
                    break;

                // ---------- Rows ----------
                case 'select_rows':
                    $this->json([
                        'success' => true,
                        'data'    => $this->db
                            ->connect($_POST['db_name'] ?? '')
                            ->selectRows(
                                $_POST['table_name'] ?? '',
                                (int)($_POST['limit']  ?? 50),
                                (int)($_POST['offset'] ?? 0)
                            ),
                    ]);
                    break;

                case 'insert_row':
                    $id = $this->db
                        ->connect($_POST['db_name'] ?? '')
                        ->insertRow(
                            $_POST['table_name'] ?? '',
                            json_decode($_POST['data'] ?? '{}', true)
                        );
                    $this->json(['success' => true, 'id' => $id]);
                    break;

                case 'update_row':
                    $rows = $this->db
                        ->connect($_POST['db_name'] ?? '')
                        ->updateRow(
                            $_POST['table_name'] ?? '',
                            json_decode($_POST['data']  ?? '{}', true),
                            json_decode($_POST['where'] ?? '{}', true)
                        );
                    $this->json(['success' => true, 'rows' => $rows]);
                    break;

                case 'delete_row':
                    $rows = $this->db
                        ->connect($_POST['db_name'] ?? '')
                        ->deleteRow(
                            $_POST['table_name'] ?? '',
                            json_decode($_POST['where'] ?? '{}', true)
                        );
                    $this->json(['success' => true, 'rows' => $rows]);
                    break;

                // ---------- Raw SQL ----------
                case 'execute_sql':
                    $dbName = trim($_POST['db_name'] ?? '');
                    $sql    = $_POST['sql'] ?? '';
                    if ($dbName === '') throw new \RuntimeException('Database name is required.');
                    $results = $this->db->connect($dbName)->executeSql($sql);
                    $this->json(['success' => true, 'results' => $results]);
                    break;

                // ---------- Code generator ----------
                case 'generate_code':
                    $dbName = trim($_POST['db_name'] ?? '');
                    if ($dbName === '') throw new \RuntimeException('Database name is required.');
                    $code = $this->db->connect($dbName)->generateConnectionCode();
                    $this->json(['success' => true, 'code' => $code]);
                    break;

                default:
                    throw new \RuntimeException('Unknown action: ' . $action);
            }
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return '';
    }

    protected function json(array $payload): void
    {
        echo json_encode($payload);
    }
}