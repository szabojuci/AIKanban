<template>
    <!-- Overlay -->
    <div
        v-if="isOpen"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center translate-y-0"
        @click.self="$emit('close')"
    >
        <!-- Modal Content -->
        <div class="relative p-5 border w-182 shadow-lg rounded-md bg-gray-500">
            <h3 class="text-lg font-semibold text-white mb-4">Add New Task</h3>
            <div class="mb-4 relative">
                <input
                    id="task-title"
                    type="text"
                    placeholder="Task Title"
                    class="w-full p-2 border rounded pr-16"
                    v-model="title"
                    maxlength="42"
                    @keyup.enter="save"
                    ref="titleInput"
                />
                <div class="absolute right-2 bottom-2 bg-gray-200 text-green-600 text-xs px-1 rounded">
                    {{ 42 - title.length }}
                </div>
            </div>

            <div class="mb-4 relative">
                <textarea
                    id="task-desc"
                    placeholder="Task Description"
                    class="w-full p-2 border rounded h-24 pb-6"
                    v-model="description"
                    maxlength="512"
                ></textarea>
                <div class="absolute right-2 bottom-2 bg-gray-200 text-green-600 text-xs px-1 rounded">
                    {{ 512 - description.length }}
                </div>
            </div>

            <div class="flex justify-end">
                <button
                    @click="$emit('close')"
                    class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded mr-2"
                >
                    Cancel
                </button>
                <button
                    @click="save"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!title"
                >
                    Add Task
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch, nextTick } from 'vue';

const props = defineProps({
    isOpen: Boolean
});

const emit = defineEmits(['close', 'save']);

const title = ref('');
const description = ref('');
const titleInput = ref(null);

watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        title.value = '';
        description.value = '';
        nextTick(() => {
            titleInput.value?.focus();
        });
    }
});

const save = () => {
    if (!title.value) return;
    // Combine title and description for now as the backend seems to take a single string description
    // or arguably we should just pass the description as the main text.
    // Looking at the prompt() usage: const desc = prompt("New Task Description:");
    // The backend `api.addTask(project, description)` takes a string.
    // I will combine them or just use title if description is empty.

    let finalDesc = title.value;
    if (description.value) {
        finalDesc += `\n\n${description.value}`;
    }

    emit('save', finalDesc);
};
</script>
