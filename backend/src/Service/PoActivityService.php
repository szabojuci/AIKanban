<?php

namespace App\Service;

use PDO;
use Exception;
use App\Config;
use App\Prompts;
use App\Service\GeminiService;

class PoActivityService
{
    private PDO $pdo;
    private GeminiService $geminiService;
    private ?int $currentUserId;
    private string $dbType;

    public function __construct(PDO $pdo, GeminiService $geminiService, string $dbType = 'sqlite')
    {
        $this->pdo = $pdo;
        $this->geminiService = $geminiService;
        $this->dbType = $dbType;
    }

    public function tick(string $projectName, ?int $userId = null): void
    {
        if (empty($projectName)) {
            return;
        }

        $this->currentUserId = $userId;

        // 1. Check if we are in "Working Hours" (8 AM - 4 PM, Weekdays)
        if (!$this->isWorkingHours()) {
            return;
        }

        // 2. Fetch project simulation metadata
        $project = $this->getProjectData($projectName);
        if (!$project) {
            return;
        }

        // 3. Process Autonomous Actions
        $this->processComments($project);
        $this->processChangeRequests($project);
    }

    private function isWorkingHours(): bool
    {
        $hour = (int)date('H');
        $dayOfWeek = (int)date('N'); // 1 (Mon) to 7 (Sun)

        // Mon-Fri, 8:00 - 15:59
        return ($dayOfWeek >= 1 && $dayOfWeek <= 5) && ($hour >= 8 && $hour < 16);
    }

    private function getProjectData(string $projectName): ?array
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT id, name, team_id, last_comment_at, last_cr_at FROM {$prefix}projects WHERE name = :name");
        $stmt->execute([':name' => $projectName]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function processComments(array $project): void
    {
        $lastAt = $project['last_comment_at'];
        $interval = 2 * 3600; // 2 hours

        if ($lastAt && (time() - strtotime($lastAt)) < $interval) {
            return;
        }

        // Trigger Comment
        try {
            $task = $this->getRandomTaskForComment($project['name']);
            if (!$task) {
                return;
            }

            $context = $this->getProjectSummary($project['name']);
            $prompt = Prompts::getPoCheckInPrompt($task['title'], $task['description'], $context);

            $this->geminiService->setContext($this->currentUserId, $project['team_id']);
            $comment = $this->geminiService->askTaipo($prompt);

            $this->addPoComment($task['id'], $comment);
            $this->updateProjectTimestamp($project['id'], 'last_comment_at');
        } catch (Exception $e) {
            error_log("PoActivityService error (Comment): " . $e->getMessage());
        }
    }

    private function processChangeRequests(array $project): void
    {
        $lastAt = $project['last_cr_at'];
        $interval = 3 * 24 * 3600; // 3 days

        if ($lastAt && (time() - strtotime($lastAt)) < $interval) {
            return;
        }

        // Trigger Change Request
        try {
            $requirements = $this->getRequirements($project['name']);
            $boardStatus = $this->getProjectSummary($project['name']);
            $prompt = Prompts::getChangeRequestPrompt($project['name'], $requirements, $boardStatus);

            $this->geminiService->setContext($this->currentUserId, $project['team_id']);
            $rawCr = $this->geminiService->askTaipo($prompt);

            $crData = $this->parseCrResponse($rawCr);
            if ($crData) {
                $this->addCrTask($project['name'], $crData['title'], $crData['story']);
                $this->updateProjectTimestamp($project['id'], 'last_cr_at');
            }
        } catch (Exception $e) {
            error_log("PoActivityService error (CR): " . $e->getMessage());
        }
    }

    private function getRandomTaskForComment(string $projectName): ?array
    {
        $prefix = Config::getTablePrefix();
        $randomFunc = ($this->dbType === 'mysql') ? 'RAND()' : 'RANDOM()';

        // Prefer tasks in WIP stages
        $query = "SELECT id, title, description FROM {$prefix}tasks
                  WHERE project_name = :name
                  AND status IN ('IMPLEMENTATION WIP:3', 'TESTING WIP:2', 'REVIEW WIP:2')
                  ORDER BY {$randomFunc} LIMIT 1";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':name' => $projectName]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            // Fallback to Backlog
            $query = "SELECT id, title, description FROM {$prefix}tasks
                      WHERE project_name = :name
                      AND status = 'SPRINT BACKLOG'
                      ORDER BY {$randomFunc} LIMIT 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':name' => $projectName]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $task ?: null;
    }

    private function getProjectSummary(string $projectName): string
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT title, status FROM {$prefix}tasks WHERE project_name = :name LIMIT 15");
        $stmt->execute([':name' => $projectName]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = "Active tasks summary:\n";
        foreach ($tasks as $t) {
            $summary .= "- [{$t['status']}] {$t['title']}\n";
        }
        return $summary;
    }

    private function getRequirements(string $projectName): string
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT content FROM {$prefix}requirements WHERE project_name = :name ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([':name' => $projectName]);
        return $stmt->fetchColumn() ?: "No requirements specified.";
    }

    private function addPoComment(int $taskId, string $comment): void
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT po_comments FROM {$prefix}tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $current = $stmt->fetchColumn() ?: "";

        $separator = $current ? "\n\n---\n\n" : "";
        $newComments = $current . $separator . "**TAIPO Check-in:**\n" . $comment;

        $update = $this->pdo->prepare("UPDATE {$prefix}tasks SET po_comments = :comments WHERE id = :id");
        $update->execute([':comments' => $newComments, ':id' => $taskId]);
    }

    private function addCrTask(string $projectName, string $title, string $description): void
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("INSERT INTO {$prefix}tasks (project_name, title, description, status, is_important, po_comments)
                                    VALUES (:name, :title, :desc, 'SPRINT BACKLOG', 1, 'TAIPO: Automated Change Request generated based on project dynamics.')");
        $stmt->execute([
            ':name' => $projectName,
            ':title' => $title,
            ':desc' => $description
        ]);
    }

    private function updateProjectTimestamp(int $projectId, string $column): void
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("UPDATE {$prefix}projects SET $column = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':id' => $projectId]);
    }

    private function parseCrResponse(string $raw): ?array
    {
        $title = "";
        $story = "";

        if (preg_match('/\[TITLE\]:(.*)/i', $raw, $m)) {
            $title = trim($m[1]);
        }
        if (preg_match('/\[STORY\]:(.*)/i', $raw, $m)) {
            $story = trim($m[1]);
        }

        if ($title && $story) {
            return ['title' => $title, 'story' => $story];
        }

        return null;
    }
}
