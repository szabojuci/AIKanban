<template>
    <div class="flex flex-nowrap overflow-x-auto gap-4 p-4 h-[calc(100vh-140px)]">
        <div v-for="(style, title) in columns" :key="title" class="min-w-[280px] flex flex-col bg-base-100 rounded-box shadow-xl h-full">

            <!-- Column Header -->
            <div :class="`p-4 rounded-t-box font-bold flex justify-between items-center bg-${getResultingColor(style)} text-primary-content`">
                <span>{{ title }}</span>
                <div class="badge badge-ghost">{{ tasks[title]?.length || 0 }}</div>
            </div>

            <!-- Add Task Button (Only for Backlog) -->
            <div v-if="title.includes('BACKLOG')" class="p-2">
                <button class="btn btn-block btn-sm btn-ghost border-dashed border-2 border-base-300" @click="addTask">
                    + Add Task
                </button>
            </div>

            <!-- Task List -->
            <div
                class="flex-1 overflow-y-auto p-2 space-y-2 min-h-[100px]"
                @dragover.prevent
                @drop="onDrop($event, title)"
            >
                <TaskCard
                    v-for="task in tasks[title]"
                    :key="task.id"
                    :task="task"
                    @dragstart="onDragStart($event, task)"
                    @delete="$emit('task-deleted', task.id)"
                    @toggle-imp="$emit('task-updated')"
                    @decompose="$emit('decompose', $event)"
                    @generate-code="$emit('generate-code', $event)"
                />

                <div v-if="!tasks[title]?.length" class="text-center p-8 text-base-content/50 italic">
                    No tasks here
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';
import TaskCard from './TaskCard.vue';
import { api } from '../services/api';

const props = defineProps({
    columns: Object,
    tasks: Object,
    currentProject: String
});

const emit = defineEmits(['task-updated', 'task-deleted', 'task-added']);

const getResultingColor = (style) => {
    // Mapping internal style names to DaisyUI/Tailwind colors if needed
    // 'info', 'danger', 'warning', 'primary', 'success' map well to DaisyUI classes
    if (style === 'danger') return 'error';
    return style;
};

const onDragStart = (event, task) => {
    event.dataTransfer.setData('taskId', task.id);
    event.dataTransfer.setData('originCol', task.status);
};

const onDrop = async (event, newStatus) => {
    const taskId = event.dataTransfer.getData('taskId');
    if (!taskId) return;

    try {
        await api.updateStatus(taskId, newStatus, props.currentProject);
        emit('task-updated');
    } catch (e) {
        alert(e.response?.data?.error || "Failed to move task");
    }
};

const addTask = async () => {
    const desc = prompt("New Task Description:");
    if (!desc) return;
    await api.addTask(props.currentProject, desc);
    emit('task-added');
};
</script>
