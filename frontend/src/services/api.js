import axios from 'axios';

// Create axios instance with base URL pointing to the proxy or direct backend
const client = axios.create({
    baseURL: '/api', // Uses Vite proxy
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

export const api = {
    async getKanbanTasks() {
        const response = await client.get('/');
        // Backend returns generic JSON, we need to adapt if structure differs
        // Expecting { tasks: {...} } or simply the tasks array/object
        // Based on current PHP logic, we need to standardize the response.
        // Let's assume the PHP will return the 'kanbanTasks' array directly.
        return response.data;
    },

    async addTask(project, description) {
        const formData = new FormData();
        formData.append('action', 'add_task');
        formData.append('current_project', project);
        formData.append('description', description);

        // PHP expects POST form-data or JSON with specific structure.
        // Let's stick to JSON since we handle it in Application.php (lines 68-75)
        return client.post('/', {
            action: 'add_task',
            current_project: project,
            description: description
        });
    },

    async updateStatus(taskId, newStatus, currentProject) {
        return client.post('/', {
            action: 'update_status',
            task_id: taskId,
            new_status: newStatus,
            current_project: currentProject
        });
    },

    async deleteTask(taskId) {
        return client.post('/', {
            action: 'delete_task',
            task_id: taskId
        });
    },

    async toggleImportance(taskId, isImportant) {
        return client.post('/', {
            action: 'toggle_importance',
            task_id: taskId,
            is_important: isImportant
        });
    }
};
