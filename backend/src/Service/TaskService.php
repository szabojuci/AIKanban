<?php

namespace App\Service;

use PDO;
use Exception;
use App\Utils;
use App\Service\GeminiService;
use App\Exception\TaskNotFoundException;
use App\Exception\WipLimitExceededException;

class TaskService
{
    private PDO $pdo;
    private GeminiService $geminiService;

    public function __construct(PDO $pdo, GeminiService $geminiService)
    {
        $this->pdo = $pdo;
        $this->geminiService = $geminiService;
    }

    public function getProjects(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT project_name FROM tasks ORDER BY project_name ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTasksByProject(string $projectName): array
    {
        $stmt = $this->pdo->prepare("SELECT id, title, description, status, is_important, generated_code, is_subtask, po_comments, position FROM tasks WHERE project_name = :projectName ORDER BY position ASC, id ASC");
        $stmt->execute([':projectName' => $projectName]);
        $tasks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tasks[] = $row;
        }
        return $tasks;
    }

    public function reorderTasks(string $projectName, string $status, array $taskIds): void
    {
        try {
            $this->pdo->beginTransaction();

            $priority = 0;
            foreach ($taskIds as $taskId) {
                // Verify task belongs to project (security check)
                $stmt = $this->pdo->prepare("UPDATE tasks SET status = :status, position = :position WHERE id = :id AND project_name = :project_name");
                $stmt->execute([
                    ':status' => $status,
                    ':position' => $priority,
                    ':id' => $taskId,
                    ':project_name' => $projectName
                ]);
                $priority++;
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function addTask(string $projectName, string $title, string $description, int $isImportant = 0): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tasks (project_name, title, description, status, is_important) VALUES (:project_name, :title, :description, 'SPRINT BACKLOG', :is_important)");
        $stmt->execute([
            ':project_name' => $projectName,
            ':title' => $title,
            ':description' => $description,
            ':is_important' => $isImportant
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteTask(int $taskId): string
    {
        // Get status before deleting
        $statusStmt = $this->pdo->prepare("SELECT status FROM tasks WHERE id = :id");
        $statusStmt->execute([':id' => $taskId]);
        $status = $statusStmt->fetchColumn();

        if ($status === false) {
            throw new TaskNotFoundException("Task not found.");
        }

        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);

        return $status;
    }

    public function toggleImportance(int $taskId, int $isImportant): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET is_important = :is_important WHERE id = :id");
        $stmt->execute([
            ':is_important' => $isImportant,
            ':id' => $taskId
        ]);
    }

    public function updateTask(int $taskId, string $title, string $description): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET title = :title, description = :description WHERE id = :id");
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':id' => $taskId
        ]);
    }

    public function updateStatus(int $taskId, string $newStatus, string $projectName): void
    {
        $wipLimit = Utils::getWIPLimit($newStatus);

        if ($wipLimit !== null) {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_name = :projectName AND status = :status");
            $countStmt->execute([
                ':projectName' => $projectName,
                ':status' => $newStatus
            ]);
            $currentTaskCount = $countStmt->fetchColumn();

            if ($currentTaskCount >= $wipLimit) {
                throw new WipLimitExceededException("WIP Limit Exceeded: The limit for '{$newStatus}' column is {$wipLimit} tasks.", 403);
            }
        }

        $stmt = $this->pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $taskId
        ]);
    }

    public function replaceProjectTasks(string $projectName, array $newTasks): int
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE project_name = :projectName");
            $stmt->execute([':projectName' => $projectName]);

            $insertStmt = $this->pdo->prepare(
                "INSERT INTO tasks (project_name, title, description, status) VALUES (:project_name, :title, :description, :status)"
            );

            $count = 0;
            foreach ($newTasks as $task) {
                // Fallback for replaceProjectTasks if source doesn't have title
                $tTitle = $task['title'] ?? '';
                $tDesc = $task['description'] ?? '';
                if (empty($tTitle) && !empty($tDesc)) {
                    // Simple split if only description is present
                    $lines = explode("\n", $tDesc);
                    $tTitle = trim($lines[0]);
                    $tDesc = count($lines) > 1 ? trim(implode("\n", array_slice($lines, 1))) : '';
                }

                $insertStmt->execute([
                    ':project_name' => $projectName,
                    ':title' => $tTitle,
                    ':description' => $tDesc,
                    ':status' => $task['status']
                ]);
                $count++;
            }

            $this->pdo->commit();
            return $count;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function generateProjectTasks(string $projectName, string $rawPrompt): int
    {
        $prompt = str_replace('{{PROJECT_NAME}}', $projectName, $rawPrompt);
        $prompt .= "\n\nPlease generate a list of user stories for this project.
                    Each user story must follow the standard format: 'As a [user], I want to [action], so that [benefit]'.
                    Format each line as: [STATUS]: User Story Text
                    Available statuses: SPRINTBACKLOG, IMPLEMENTATION, TESTING, REVIEW, DONE.
                    Example: [SPRINTBACKLOG]: As a user, I want to log in, so that I can access my profile.";

        $rawText = $this->geminiService->askTaipo($prompt);
        $lines = explode("\n", $rawText);
        $newTasks = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $status = 'SPRINTBACKLOG';
            $description = $line;

            if (preg_match('/^\[(SPRINTBACKLOG|IMPLEMENTATION|TESTING|REVIEW|DONE)\]:\s*(.*)/iu', $line, $matches)) {
                $rawStatus = strtoupper($matches[1]);
                $description = trim($matches[2]);

                switch ($rawStatus) {
                    case 'SPRINTBACKLOG':
                        $status = 'SPRINT BACKLOG';
                        break;
                    case 'IMPLEMENTATION':
                        $status = 'IMPLEMENTATION WIP:3';
                        break;
                    case 'TESTING':
                        $status = 'TESTING WIP:2';
                        break;
                    case 'REVIEW':
                        $status = 'REVIEW WIP:2';
                        break;
                    case 'DONE':
                        $status = 'DONE';
                        break;
                    default:
                        $status = 'SPRINT BACKLOG'; // Safe fallback
                }
            }

            if (!empty($description) && strlen($description) > 5) {
                $newTasks[] = [
                    'description' => $description,
                    'status' => $status
                ];
            }
        }

        return $this->replaceProjectTasks($projectName, $newTasks);
    }

    public function decomposeTask(string $description, string $projectName): int
    {
        $prompt = "Decompose this user story into 3-5 concrete technical subtasks: '{$description}'.
                    Each subtask must be a User Story following the standard format: 'As a [actor], I want to [action], so that [benefit]'.
                    Your response must ONLY be the list of tasks, with each task on a new line. Do not include statuses.";

        $rawTasks = $this->geminiService->askTaipo($prompt);
        $lines = explode("\n", $rawTasks);
        $count = 0;

        $stmt = $this->pdo->prepare("INSERT INTO tasks (project_name, title, description, status, is_subtask, po_comments) VALUES (?, ?, ?, 'SPRINT BACKLOG', 1, ?)");

        $poFeedback = "TAIPO: Based on original story: \"{$description}\"";

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line) {
                // For subtasks, we'll use the user story as the title for now
                $stmt->execute([$projectName, $line, "", $poFeedback]);
                $count++;
            }
        }
        return $count;
    }

    public function queryTask(int $taskId, string $query): string
    {
        // 1. Fetch current task details INCLUDING project_name
        $stmt = $this->pdo->prepare("SELECT description, po_comments, project_name, status FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            throw new TaskNotFoundException("Task not found.");
        }

        $projectName = $task['project_name'];

        // 2. Fetch all OTHER tasks in the same project to provide context
        $stmtAll = $this->pdo->prepare("SELECT id, description, status FROM tasks WHERE project_name = :project_name ORDER BY status, id");
        $stmtAll->execute([':project_name' => $projectName]);
        $allTasks = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // 3. Construct Project Context String
        $projectContext = "Project Name: " . $projectName . "\n";
        $projectContext .= "Other Tasks in Project:\n";
        foreach ($allTasks as $t) {
            // Mark the current task distinctly
            $marker = ($t['id'] == $taskId) ? " (CURRENT TASK)" : "";
            $projectContext .= "- [{$t['status']}] {$t['description']}{$marker}\n";
        }

        // 4. Construct Task Specific Context
        $taskContext = "Current Task Description: " . $task['description'];
        $taskContext .= "\nCurrent Task Status: " . $task['status'];
        if (!empty($task['po_comments'])) {
            $taskContext .= "\nProduct Owner Comments: " . $task['po_comments'];
        }

        // 5. Build Final Prompt
        $prompt = "You are TAIPO, an intelligent coding assistant for the project '{$projectName}'.

        Global Project Context:
        {$projectContext}

        Focus on this Specific Task:
        {$taskContext}

        User Question:
        {$query}

        Instructions:
        - Answer the user's question specifically related to the current task.
        - Use the global project context to understand dependencies or overall goals, but focus on the specific task.
        - Refrain from lengthy intros.
        - Provide code snippets if asked.";

        $answer = $this->geminiService->askTaipo($prompt);

        // Persist the Q&A to po_comments
        $currentComments = $task['po_comments'] ?? '';
        $separator = $currentComments ? "\n\n---\n\n" : "";
        $newEntry = "**Q:** {$query}\n**A:** {$answer}";
        $newComments = $currentComments . $separator . $newEntry;

        $updateStmt = $this->pdo->prepare("UPDATE tasks SET po_comments = :comments WHERE id = :id");
        $updateStmt->execute([':comments' => $newComments, ':id' => $taskId]);

        return $answer;
    }

    public function generateJavaCode(string $description): string
    {
        $prompt = "Generate a **complete, but very concise** Java class or function to solve the task: '{$description}'. The code should be **functional**, but only include the necessary imports and logic. Do not generate long explanatory comments or introduction text! Use a single Markdown code block (```java ... ```).";

        $rawText = $this->geminiService->askTaipo($prompt);
        return Utils::formatCodeBlocks($rawText);
    }
}
