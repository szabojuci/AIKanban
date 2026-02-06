<?php

namespace App\Controller;

use App\Service\ProjectService;
use App\Config;
use Exception;
use App\Exception\ProjectNotFoundException;
use App\Exception\ProjectAlreadyExistsException;

class ProjectController
{
    private ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function handleList()
    {
        try {
            $projects = $this->projectService->getAllProjects();
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'projects' => $projects]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleCreate()
    {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project name is required']);
            return;
        }

        try {
            $id = $this->projectService->createProject($name);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (ProjectAlreadyExistsException $e) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleUpdate()
    {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');

        if (!$id || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID and name are required']);
            return;
        }

        try {
            $this->projectService->renameProject((int)$id, $name);
            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (ProjectNotFoundException $e) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (ProjectAlreadyExistsException $e) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleDelete()
    {
        $id = $_POST['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            return;
        }

        try {
            $this->projectService->deleteProject((int)$id);
            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (ProjectNotFoundException $e) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
