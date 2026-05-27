<?php

namespace App\Configuration;

class RolePermissions
{
    public const ROLE_INSTRUCTOR = 'Instructor';
    public const ROLE_PRODUCT_OWNER = 'Product Owner';
    public const ROLE_STUDENT = 'Student';

    /**
     * Maps each action to the roles that are allowed to perform it.
     * If an action is not listed here, it is allowed for all authenticated users.
     */
    private const ACTION_ROLES = [
        // Instructor-only
        'delete_project' => [self::ROLE_INSTRUCTOR],
        'toggle_project_activity' => [self::ROLE_INSTRUCTOR],
        'set_project_team' => [self::ROLE_INSTRUCTOR],
        'save_setting' => [self::ROLE_INSTRUCTOR],
        'export_backup' => [self::ROLE_INSTRUCTOR],
        'import_backup' => [self::ROLE_INSTRUCTOR],

        // PO + Instructor
        'add_task' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'delete_task' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'toggle_importance' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'decompose_task' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'generate_project_tasks' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'create_project_from_spec' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'review_task' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'refine_task' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'suggest_priority' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'save_requirement' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'create_project' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],
        'update_project' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER],

        // All roles (Student + PO + Instructor)
        'edit_task' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
        'update_status' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
        'reorder_tasks' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
        'generate_code' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
        'commit_to_github' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
        'query_task' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
        'get_task_history' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
        'get_project_history' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
        'get_requirements' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
        'get_api_usage' => [self::ROLE_INSTRUCTOR, self::ROLE_PRODUCT_OWNER, self::ROLE_STUDENT],
    ];

    /**
     * Actions that are blocked when a task is in DONE status.
     */
    private const DONE_BLOCKED_ACTIONS = [
        'edit_task',
        'delete_task',
        'toggle_importance',
        'decompose_task',
        'generate_code',
        'refine_task',
        'suggest_priority',
    ];

    /**
     * Check if a given role is allowed to perform a given action.
     */
    public static function isAllowed(string $role, string $action): bool
    {
        if (!isset(self::ACTION_ROLES[$action])) {
            return true;
        }
        return in_array($role, self::ACTION_ROLES[$action]);
    }

    /**
     * Returns all action names the given role is allowed to perform.
     */
    public static function getAllowedActions(string $role): array
    {
        $allowed = [];
        foreach (self::ACTION_ROLES as $action => $roles) {
            if (in_array($role, $roles)) {
                $allowed[] = $action;
            }
        }
        return $allowed;
    }

    /**
     * Check if an action is blocked based on the task's current status.
     */
    public static function isBlockedByStatus(string $action, string $status): bool
    {
        if ($status === 'DONE' && in_array($action, self::DONE_BLOCKED_ACTIONS)) {
            return true;
        }
        // Decompose only makes sense from the backlog
        if ($action === 'decompose_task' && $status !== 'SPRINT BACKLOG') {
            return true;
        }
        return false;
    }
}
