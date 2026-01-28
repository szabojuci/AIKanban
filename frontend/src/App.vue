<template>
    <div class="min-h-screen bg-base-200" data-theme="cupcake">
        <!-- Navbar -->
        <div class="navbar bg-base-100 shadow-md mb-8">
            <div class="flex-1">
                <a class="btn btn-ghost text-xl">🤖 AI-Driven Kanban</a>
            </div>
            <div class="flex-none gap-2">
                <div class="form-control">
                    <!-- Project Selector could go here -->
                </div>
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
                        <div class="w-10 rounded-full">
                            <img alt="Tailwind CSS Navbar component" src="./images/tailwind-css-icon.png">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="container mx-auto px-4">
            <div v-if="loading" class="flex justify-center p-10">
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <div v-else-if="error" role="alert" class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>Error: {{ error }}</span>
            </div>

            <KanbanBoard v-else
                :columns="columns"
                :tasks="tasks"
                @task-updated="refreshTasks"
                @task-deleted="refreshTasks"
                @task-added="refreshTasks"
            />
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import KanbanBoard from './components/KanbanBoard.vue';
import { api } from './services/api';

const loading = ref(true);
const error = ref(null);
const tasks = ref({});
const columns = ref({
    'SPRINT BACKLOG': 'info',
    'IMPLEMENTATION WIP:3': 'error',
    'TESTING WIP:2': 'warning',
    'REVIEW WIP:2': 'primary',
    'DONE': 'success',
});

const refreshTasks = async () => {
    try {
        tasks.value = await api.getKanbanTasks();
    } catch (e) {
        error.value = e.message;
    }
};

onMounted(async () => {
    try {
        loading.value = true;
        await refreshTasks();
    } catch (e) {
        error.value = "Failed to load tasks. Ensure backend is running.";
    } finally {
        loading.value = false;
    }
});
</script>
