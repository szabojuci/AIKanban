<?php

namespace App\Service;

use PDO;
use Exception;
use App\Config;
use App\Configuration\DatabaseConfig;

class BackupService
{
    private PDO $pdo;

    // Fixed order of tables child-to-parent for deletion
    private const DELETE_ORDER = [
        'task_history',
        'team_users',
        'tasks',
        'requirements',
        'projects',
        'users',
        'teams',
        'roles',
        'settings',
        'tawos_issues',
        'api_usage'
    ];

    // Fixed order of tables parent-to-child for insertion
    private const INSERT_ORDER = [
        'roles',
        'teams',
        'users',
        'projects',
        'requirements',
        'tasks',
        'team_users',
        'task_history',
        'settings',
        'tawos_issues',
        'api_usage'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Exports all database tables (with schema prefix mapping) into a structured JSON string.
     */
    public function exportBackup(): string
    {
        $prefix = Config::getTablePrefix();
        $schema = DatabaseConfig::get()['schema'];

        $backup = [
            'version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'tables' => []
        ];

        foreach (array_keys($schema) as $tableName) {
            $prefixedName = $prefix . $tableName;
            $stmt = $this->pdo->query("SELECT * FROM {$prefixedName}");
            $backup['tables'][$tableName] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Restores the database from a structured JSON backup string.
     * Uses transactional integrity to ensure clean rollback on any failure.
     */
    public function importBackup(string $jsonData): bool
    {
        $data = json_decode($jsonData, true);
        if (!$data || !isset($data['version']) || !isset($data['tables']) || !is_array($data['tables'])) {
            throw new \InvalidArgumentException("Invalid backup data format.");
        }

        $prefix = Config::getTablePrefix();
        $driver = strtolower($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $quote = ($driver === 'mysql') ? '`' : '"';

        $this->pdo->beginTransaction();
        try {
            $this->setForeignKeyChecks(false);

            // 1. Delete all existing data in reverse dependency order
            foreach (self::DELETE_ORDER as $tableName) {
                $prefixedName = $prefix . $tableName;
                $this->pdo->exec("DELETE FROM {$prefixedName}");
            }

            // 2. Insert rows in dependency order
            foreach (self::INSERT_ORDER as $tableName) {
                if (!isset($data['tables'][$tableName]) || !is_array($data['tables'][$tableName])) {
                    continue;
                }

                $rows = $data['tables'][$tableName];
                if (empty($rows)) {
                    continue;
                }

                $columns = array_keys($rows[0]);
                $colsStr = implode(', ', array_map(fn($c) => "{$quote}{$c}{$quote}", $columns));
                $placeholders = implode(', ', array_map(fn($c) => ":{$c}", $columns));

                $prefixedName = $prefix . $tableName;
                $sql = "INSERT INTO {$prefixedName} ({$colsStr}) VALUES ({$placeholders})";
                $stmt = $this->pdo->prepare($sql);

                foreach ($rows as $row) {
                    $stmt->execute($row);
                }
            }

            $this->setForeignKeyChecks(true);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Utility method to safely toggle foreign key checks across primary DB drivers.
     */
    private function setForeignKeyChecks(bool $enable): void
    {
        $driver = strtolower($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'mysql') {
            $this->pdo->exec($enable ? 'SET FOREIGN_KEY_CHECKS = 1' : 'SET FOREIGN_KEY_CHECKS = 0');
        } elseif ($driver === 'sqlite') {
            $this->pdo->exec($enable ? 'PRAGMA foreign_keys = ON' : 'PRAGMA foreign_keys = OFF');
        }
    }
}
