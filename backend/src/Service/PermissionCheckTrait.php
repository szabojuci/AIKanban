<?php

namespace App\Service;

use App\Config;
use App\Configuration\RolePermissions;

/**
 * Provides centralized role-based and status-based permission checking.
 * Used by Application to enforce RBAC before dispatching to controllers.
 *
 * @property TaskService $taskService
 * @property TeamService $teamService
 */
trait PermissionCheckTrait
{
    /**
     * Centralized role-based permission check.
     * Resolves the project context from the request, determines the user's role,
     * and checks if the action is allowed for that role.
     * Returns true if allowed, false if blocked (and sends 403 response).
     */
    private function checkActionPermission(string $action): bool
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $denyReason = null;
        $isInstructor = $_SESSION['is_instructor'] ?? false;

        if (!$isInstructor) {
            $projectName = $this->resolveProjectFromRequest();

            if (!empty($projectName)) {
                $role = $this->resolveUserRole($userId, $projectName);

                if (!RolePermissions::isAllowed($role, $action)) {
                    $denyReason = 'You do not have permission to perform this action.';
                }
            }

            if ($denyReason === null) {
                $denyReason = $this->checkTaskStatusPermission($action);
            }
        }

        if ($denyReason !== null) {
            header(Config::APP_JSON, true, 403);
            echo json_encode(['success' => false, 'error' => $denyReason]);
        }

        return $denyReason === null;
    }

    /**
     * Checks if the action is blocked based on the task's current status.
     * Returns the deny reason string if blocked, or null if allowed.
     */
    private function checkTaskStatusPermission(string $action): ?string
    {
        $taskId = filter_var($_POST['task_id'] ?? $_GET['task_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$taskId) {
            return null;
        }

        $task = $this->taskService->getTaskById($taskId);
        if ($task && RolePermissions::isBlockedByStatus($action, $task['status'])) {
            return 'This action is not allowed on tasks with status: ' . $task['status'];
        }

        return null;
    }

    /**
     * Attempts to determine the current project name from various request parameters.
     */
    private function resolveProjectFromRequest(): ?string
    {
        $projectName = trim(
            $_POST['current_project']
                ?? $_POST['project_name']
                ?? $_GET['project_name']
                ?? $_GET['project']
                ?? ''
        );

        if (!empty($projectName)) {
            return $projectName;
        }

        // Resolve from task_id if available
        $taskId = filter_var($_POST['task_id'] ?? $_GET['task_id'] ?? null, FILTER_VALIDATE_INT);
        if ($taskId) {
            $task = $this->taskService->getTaskById($taskId);
            if ($task) {
                return $task['project_name'];
            }
        }

        return null;
    }

    /**
     * Resolves the effective role for a user in a given project.
     * Global instructors get Instructor role; otherwise resolved via TeamService.
     */
    public function resolveUserRole(int $userId, string $projectName): string
    {
        if ($_SESSION['is_instructor'] ?? false) {
            return RolePermissions::ROLE_INSTRUCTOR;
        }

        $role = $this->teamService->getUserRoleForProject($userId, $projectName);

        // Fallback: if user is authorized but has no explicit role, default to Student
        return $role ?? RolePermissions::ROLE_STUDENT;
    }
}
