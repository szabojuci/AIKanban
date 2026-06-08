<?php

namespace Tests\Unit;

use PDO;
use App\Configuration\DatabaseConfig;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the DatabaseConfig class to verify schema definitions
 * and the Database class connectivity with SQLite.
 */
class DatabaseTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        // Use in-memory SQLite for fast, isolated testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testDatabaseConfigReturnsSchemaArray(): void
    {
        $config = DatabaseConfig::get();
        $this->assertArrayHasKey('schema', $config);
        $this->assertArrayHasKey('pragma', $config);
    }

    public function testSchemaContainsAllExpectedTables(): void
    {
        $config = DatabaseConfig::get();
        $expectedTables = ['settings', 'roles', 'teams', 'users', 'projects', 'tasks', 'requirements', 'api_usage', 'team_users'];

        foreach ($expectedTables as $table) {
            $this->assertArrayHasKey($table, $config['schema'], "Missing table definition: $table");
        }
    }

    public function testSchemaTablesCanBeCreatedInSqlite(): void
    {
        $config = DatabaseConfig::get();

        // Apply pragma
        foreach ($config['pragma'] as $key => $value) {
            $this->pdo->exec("PRAGMA $key = $value");
        }

        // Create all tables
        foreach ($config['schema'] as $createSql) {
            $this->pdo->exec($createSql);
        }

        // Verify tables exist by querying sqlite_master
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('users', $tables);
        $this->assertContains('tasks', $tables);
        $this->assertContains('projects', $tables);
        $this->assertContains('teams', $tables);
        $this->assertContains('roles', $tables);
    }

    public function testUsersTableHasCorrectColumns(): void
    {
        $config = DatabaseConfig::get();
        $this->pdo->exec($config['schema']['users']);

        $cols = $this->getColumnNames('users');
        $this->assertContains('id', $cols);
        $this->assertContains('username', $cols);
        $this->assertContains('password_hash', $cols);
        $this->assertContains('is_instructor', $cols);
    }

    public function testTasksTableHasCorrectColumns(): void
    {
        // Must create dependent tables first
        $config = DatabaseConfig::get();
        $this->pdo->exec($config['schema']['teams']);
        $this->pdo->exec($config['schema']['projects']);
        $this->pdo->exec($config['schema']['tasks']);

        $cols = $this->getColumnNames('tasks');
        $this->assertContains('id', $cols);
        $this->assertContains('project_name', $cols);
        $this->assertContains('title', $cols);
        $this->assertContains('description', $cols);
        $this->assertContains('status', $cols);
        $this->assertContains('is_important', $cols);
        $this->assertContains('is_subtask', $cols);
        $this->assertContains('parent_id', $cols);
        $this->assertContains('po_comments', $cols);
        $this->assertContains('generated_code', $cols);
        $this->assertContains('position', $cols);
    }

    public function testProjectsTableHasCorrectColumns(): void
    {
        $config = DatabaseConfig::get();
        $this->pdo->exec($config['schema']['teams']);
        $this->pdo->exec($config['schema']['projects']);

        $cols = $this->getColumnNames('projects');
        $this->assertContains('id', $cols);
        $this->assertContains('name', $cols);
        $this->assertContains('user_id', $cols);
        $this->assertContains('team_id', $cols);
        $this->assertContains('last_comment_at', $cols);
        $this->assertContains('last_cr_at', $cols);
    }

    public function testPragmaSettings(): void
    {
        $config = DatabaseConfig::get();
        $this->assertArrayHasKey('encoding', $config['pragma']);
        $this->assertArrayHasKey('foreign_keys', $config['pragma']);
        $this->assertArrayHasKey('journal_mode', $config['pragma']);
    }

    private function getColumnNames(string $table): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info($table)");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
    }
}
