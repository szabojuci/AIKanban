<?php

namespace Tests\Unit;

use PDO;
use App\Configuration\DatabaseConfig;
use App\Exception\ProjectUnauthorizedException;
use App\Service\GeminiService;
use App\Service\TaskService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TaskService: CRUD operations, status updates,
 * importance toggling, task ordering, and WIP limit enforcement.
 */
class TaskServiceTest extends TestCase
{
    private PDO $pdo;
    private TaskService $taskService;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['TABLE_PREFIX'] = '';
        $_ENV['GEMINI_API_KEY'] = 'AIzaSyTEST_FAKE_KEY';
        $_ENV['GEMINI_BASE_URL'] = 'https://example.com/api';
        $_ENV['GEMINI_BASE_MODEL'] = 'test-model';

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create schema
        $config = DatabaseConfig::get();
        foreach ($config['pragma'] as $key => $value) {
            $this->pdo->exec("PRAGMA $key = $value");
        }
        foreach ($config['schema'] as $sql) {
            $this->pdo->exec($sql);
        }

        // Create a test user and project
        $this->pdo->exec("INSERT INTO users (username, password_hash) VALUES ('testuser', 'hash')");
        $this->pdo->exec("INSERT INTO projects (name, user_id) VALUES ('TestProject', 1)");

        $geminiService = new GeminiService(null);
        $this->taskService = new TaskService($this->pdo, $geminiService);
    }

    public function testAddTaskCreatesTaskInSprint(): void
    {
        $id = $this->taskService->addTask('TestProject', 'My Title', 'My Description', 0, 1, true);
        $this->assertGreaterThan(0, $id);

        $task = $this->taskService->getTaskById($id);
        $this->assertNotNull($task);
        $this->assertSame('My Title', $task['title']);
        $this->assertSame('My Description', $task['description']);
        $this->assertSame('SPRINT BACKLOG', $task['status']);
    }

    public function testGetTaskByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->taskService->getTaskById(9999));
    }

    public function testGetTasksByProjectReturnsCorrectTasks(): void
    {
        $this->taskService->addTask('TestProject', 'Task 1', 'Desc 1', 0, 1, true);
        $this->taskService->addTask('TestProject', 'Task 2', 'Desc 2', 0, 1, true);

        $tasks = $this->taskService->getTasksByProject('TestProject', 1, true);
        $this->assertCount(2, $tasks);
    }

    public function testGetTasksByProjectReturnsEmptyForUnauthorized(): void
    {
        $this->taskService->addTask('TestProject', 'Task', 'Desc', 0, 1, true);

        // User 2 has no access to this project (not instructor, not team member)
        $tasks = $this->taskService->getTasksByProject('TestProject', 2, false);
        $this->assertEmpty($tasks);
    }

    public function testDeleteTaskRemovesTask(): void
    {
        $id = $this->taskService->addTask('TestProject', 'ToDelete', 'Will be deleted', 0, 1, true);
        $this->assertNotNull($this->taskService->getTaskById($id));

        $status = $this->taskService->deleteTask($id, 1, true);
        $this->assertSame('SPRINT BACKLOG', $status);
        $this->assertNull($this->taskService->getTaskById($id));
    }

    public function testToggleImportanceUpdatesPriority(): void
    {
        $id = $this->taskService->addTask('TestProject', 'Prio Task', 'Test', 0, 1, true);

        $rows = $this->taskService->toggleImportance($id, 3, 1, true);
        $this->assertSame(1, $rows);

        $task = $this->taskService->getTaskById($id);
        $this->assertEquals(3, $task['is_important']);
    }

    public function testToggleImportanceReturnsZeroForMissingTask(): void
    {
        $rows = $this->taskService->toggleImportance(9999, 1, 1, true);
        $this->assertSame(0, $rows);
    }

    public function testUpdateTaskChangesContent(): void
    {
        $id = $this->taskService->addTask('TestProject', 'Original', 'OrigDesc', 0, 1, true);

        $rows = $this->taskService->updateTask($id, 'Updated Title', 'Updated Desc', null, 1, true);
        $this->assertSame(1, $rows);

        $task = $this->taskService->getTaskById($id);
        $this->assertSame('Updated Title', $task['title']);
        $this->assertSame('Updated Desc', $task['description']);
    }

    public function testReorderTasksUpdatePositionAndStatus(): void
    {
        $id1 = $this->taskService->addTask('TestProject', 'T1', 'D1', 0, 1, true);
        $id2 = $this->taskService->addTask('TestProject', 'T2', 'D2', 0, 1, true);

        // Move both to Implementation (reversed order)
        $this->taskService->reorderTasks('TestProject', 'IMPLEMENTATION WIP:3', [$id2, $id1], 1, true);

        $t1 = $this->taskService->getTaskById($id1);
        $t2 = $this->taskService->getTaskById($id2);

        $this->assertSame('IMPLEMENTATION WIP:3', $t1['status']);
        $this->assertSame('IMPLEMENTATION WIP:3', $t2['status']);
        // id2 should be position 0, id1 position 1
        $this->assertEquals(1, $t1['position']);
        $this->assertEquals(0, $t2['position']);
    }

    public function testUpdateStatusEnforcesWipLimit(): void
    {
        // IMPLEMENTATION WIP:3 has a limit of 3
        $this->taskService->addTask('TestProject', 'W1', 'D', 0, 1, true);
        $this->taskService->addTask('TestProject', 'W2', 'D', 0, 1, true);
        $this->taskService->addTask('TestProject', 'W3', 'D', 0, 1, true);

        // Move 3 tasks to IMPLEMENTATION manually
        $tasks = $this->taskService->getTasksByProject('TestProject', 1, true);
        foreach ($tasks as $t) {
            $this->taskService->updateStatus($t['id'], 'IMPLEMENTATION WIP:3', 'TestProject', 1, true);
        }

        // Adding a 4th should exceed WIP limit
        $id4 = $this->taskService->addTask('TestProject', 'W4', 'D', 0, 1, true);

        $this->expectException(\App\Exception\WipLimitExceededException::class);
        $this->taskService->updateStatus($id4, 'IMPLEMENTATION WIP:3', 'TestProject', 1, true);
    }

    public function testReplaceProjectTasksReplacesAll(): void
    {
        $this->taskService->addTask('TestProject', 'Old1', 'D1', 0, 1, true);
        $this->taskService->addTask('TestProject', 'Old2', 'D2', 0, 1, true);

        $newTasks = [
            ['title' => 'New1', 'description' => 'ND1', 'status' => 'SPRINT BACKLOG'],
            ['title' => 'New2', 'description' => 'ND2', 'status' => 'SPRINT BACKLOG'],
            ['title' => 'New3', 'description' => 'ND3', 'status' => 'SPRINT BACKLOG'],
        ];

        $count = $this->taskService->replaceProjectTasks('TestProject', $newTasks, 1, true);
        $this->assertSame(3, $count);

        $tasks = $this->taskService->getTasksByProject('TestProject', 1, true);
        $this->assertCount(3, $tasks);
        $this->assertSame('New1', $tasks[0]['title']);
    }
}
