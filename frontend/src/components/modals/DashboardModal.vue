<template>
    <dialog
        :class="{ 'modal-open': isOpen }"
        class="modal"
        id="dashboard_modal"
    >
        <div class="modal-box w-11/12 max-w-5xl">
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                </svg>
                Instructor Dashboard
            </h3>

            <!-- Loading -->
            <div
                v-if="loading"
                class="flex justify-center p-10"
            >
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <!-- Error -->
            <div
                v-else-if="error"
                class="alert alert-error mb-4"
            >
                {{ error }}
            </div>

            <!-- Content -->
            <div v-else>
                <!-- Tabs -->
                <div class="tabs tabs-boxed mb-4">
                    <a
                        :class="{ 'tab-active': activeTab === 'config' }"
                        @click="activeTab = 'config'"
                        class="tab"
                    >
                        Configuration
                    </a>
                    <a
                        :class="{ 'tab-active': activeTab === 'tawos' }"
                        @click="activeTab = 'tawos'"
                        class="tab"
                    >
                        TAWOS Dataset
                    </a>
                    <a
                        :class="{ 'tab-active': activeTab === 'projects' }"
                        @click="activeTab = 'projects'"
                        class="tab"
                    >
                        Projects
                    </a>
                </div>

                <!-- Tab: Configuration -->
                <div
                    v-if="activeTab === 'config'"
                    class="max-h-[60vh] overflow-y-auto space-y-2"
                >
                    <div
                        v-for="(items, group) in dashboardData.config"
                        :key="group"
                        class="collapse collapse-arrow bg-base-200 rounded-box"
                    >
                        <input
                            :checked="expandedGroups[group]"
                            @change="expandedGroups[group] = !expandedGroups[group]"
                            type="checkbox"
                        >
                        <div class="collapse-title font-bold text-sm">
                            {{ group }}
                            <span class="badge badge-sm badge-ghost ml-2">
                                {{ Object.keys(items).length }}
                            </span>
                        </div>
                        <div class="collapse-content">
                            <table class="table table-xs w-full">
                                <thead>
                                    <tr>
                                        <th class="w-1/2">Key</th>
                                        <th class="w-1/2">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(value, key) in items"
                                        :key="key"
                                    >
                                        <td class="font-mono text-xs opacity-80">
                                            {{ key }}
                                        </td>
                                        <td>
                                            <span
                                                v-if="isMasked(value)"
                                                class="badge badge-warning badge-sm font-mono"
                                            >
                                                {{ value }}
                                            </span>
                                            <span
                                                v-else-if="isBooleanLike(value)"
                                                :class="value === 'true' ? 'badge-success' : 'badge-error'"
                                                class="badge badge-sm"
                                            >
                                                {{ value }}
                                            </span>
                                            <span
                                                v-else
                                                class="font-mono text-xs"
                                            >
                                                {{ value || '—' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: TAWOS Dataset -->
                <div
                    v-if="activeTab === 'tawos'"
                    class="max-h-[60vh] overflow-y-auto"
                >
                    <div
                        v-if="dashboardData.tawos"
                        class="space-y-4"
                    >
                        <!-- Stats Overview -->
                        <div class="stats shadow w-full">
                            <div class="stat">
                                <div class="stat-title">Total Records</div>
                                <div class="stat-value text-primary">
                                    {{ dashboardData.tawos.total?.toLocaleString() }}
                                </div>
                                <div class="stat-desc">Agile issues from TAWOS</div>
                            </div>
                            <div class="stat">
                                <div class="stat-title">Issue Types</div>
                                <div class="stat-value text-secondary">
                                    {{ dashboardData.tawos.types?.length || 0 }}
                                </div>
                                <div class="stat-desc">Distinct categories</div>
                            </div>
                            <div class="stat">
                                <div class="stat-title">Source Projects</div>
                                <div class="stat-value text-accent">
                                    {{ dashboardData.tawos.projects?.length || 0 }}
                                </div>
                                <div class="stat-desc">Open-source projects</div>
                            </div>
                        </div>

                        <!-- Type Breakdown -->
                        <div class="bg-base-200 p-4 rounded-box">
                            <h4 class="font-bold mb-3">Issue Type Distribution</h4>
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="typeItem in dashboardData.tawos.types"
                                    :key="typeItem.type"
                                    :class="getTypeBadgeClass(typeItem.type)"
                                    class="badge badge-lg gap-2"
                                >
                                    {{ typeItem.type }}
                                    <span class="font-bold">{{ typeItem.count }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Source Projects -->
                        <div class="bg-base-200 p-4 rounded-box">
                            <h4 class="font-bold mb-3">Source Projects</h4>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="project in dashboardData.tawos.projects"
                                    :key="project"
                                    class="badge badge-outline badge-sm"
                                >
                                    {{ project }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-center opacity-50 p-8"
                    >
                        No TAWOS data available. Dataset may not be seeded.
                    </div>
                </div>

                <!-- Tab: Projects -->
                <div
                    v-if="activeTab === 'projects'"
                    class="max-h-[60vh] overflow-y-auto"
                >
                    <table
                        v-if="dashboardData.projects?.length"
                        class="table table-zebra w-full"
                    >
                        <thead>
                            <tr class="text-base">
                                <th>ID</th>
                                <th>Name</th>
                                <th>Team</th>
                                <th>Simulation Active</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="project in dashboardData.projects"
                                :key="project.id"
                            >
                                <td class="font-mono text-xs">{{ project.id }}</td>
                                <td class="font-bold">{{ project.name }}</td>
                                <td>
                                    <span
                                        v-if="project.team_id"
                                        class="badge badge-info badge-sm"
                                    >
                                        Team #{{ project.team_id }}
                                    </span>
                                    <span
                                        v-else
                                        class="opacity-40"
                                    >—</span>
                                </td>
                                <td>
                                    <input
                                        :checked="project.is_active == 1"
                                        @change="toggleProjectActivity(project, $event)"
                                        type="checkbox"
                                        class="toggle toggle-sm toggle-success"
                                    >
                                </td>
                                <td class="text-xs opacity-60">
                                    {{ formatDate(project.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-else
                        class="text-center opacity-50 p-8"
                    >
                        No projects found.
                    </div>
                </div>
            </div>

            <div class="modal-action">
                <button
                    @click="closeModal"
                    class="btn"
                >
                    Close
                </button>
            </div>
        </div>
    </dialog>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import { api } from '../../services/api';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref(null);
const activeTab = ref('config');
const dashboardData = ref({
    config: {},
    tawos: null,
    projects: []
});

// Track which config groups are expanded (first group open by default)
const expandedGroups = reactive({});

watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        await fetchDashboard();
    }
});

const fetchDashboard = async () => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.getDashboard();
        if (res.success) {
            dashboardData.value = res;
            // Expand the first group by default
            const groups = Object.keys(res.config || {});
            groups.forEach((g, i) => {
                expandedGroups[g] = i === 0;
            });
        } else {
            error.value = res.error || 'Failed to load dashboard data.';
        }
    } catch (e) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        loading.value = false;
    }
};

const isMasked = (value) => {
    return typeof value === 'string' && value.includes('•');
};

const isBooleanLike = (value) => {
    return value === 'true' || value === 'false';
};

const getTypeBadgeClass = (type) => {
    const map = {
        'Story': 'badge-primary',
        'Bug': 'badge-error',
        'Task': 'badge-info',
        'Epic': 'badge-secondary',
        'Sub-task': 'badge-accent',
    };
    return map[type] || 'badge-ghost';
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString();
    } catch {
        return dateStr;
    }
};

const toggleProjectActivity = async (project, event) => {
    const isActive = event.target.checked;
    try {
        await api.toggleProjectActivity(project.id, isActive);
        project.is_active = isActive ? 1 : 0;
    } catch (e) {
        // Revert on failure
        event.target.checked = !isActive;
        console.error('Failed to toggle project activity:', e);
    }
};

const closeModal = () => {
    emit('close');
};
</script>
