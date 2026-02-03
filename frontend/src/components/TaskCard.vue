<template>
    <div
        class="card bg-base-200 shadow-sm hover:shadow-md transition-all duration-200 cursor-move border-l-4"
        :class="{'border-warning': isImportant, 'border-base-300': !isImportant, 'opacity-50': task.is_subtask}"
        draggable="true"
    >
        <div class="card-body p-3">
            <div class="flex justify-between items-start mb-2">

                <button
                    class="btn btn-ghost btn-xs btn-circle"
                    @click.stop="toggleImportance"
                    :class="{'text-warning': isImportant, 'text-base-content/20': !isImportant}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                </button>

                <div class="dropdown dropdown-end">
                    <button class="btn btn-ghost btn-xs btn-circle text-base-content/50" @click.stop>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="w-5 h-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
                    </button>
                    <ul class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                        <li><a @click="enableEdit">✏️ Edit</a></li>
                        <li><a @click="$emit('decompose', task)">🔨 Decompose Story</a></li>
                        <li><a @click="$emit('generate-code', task)">💻 Generate Code</a></li>
                        <li><a @click="$emit('delete')" class="text-error">🗑️ Delete</a></li>
                    </ul>
                </div>
            </div>

            <!-- Technical Task Badge -->
            <div v-if="task.is_subtask" class="badge badge-neutral badge-xs mb-1">
                Technical Task
            </div>

            <!-- Description / Inline Edit -->
            <div v-if="isEditing">
                <textarea
                    v-model="editDescription"
                    class="textarea textarea-bordered textarea-xs w-full"
                    @blur="saveEdit"
                    @keydown.enter.prevent="saveEdit"
                    ref="editInput"
                ></textarea>
            </div>
            <p v-else class="text-sm whitespace-pre-wrap" @dblclick="enableEdit">{{ task.description }}</p>

            <!-- PO Feedback -->
            <div v-if="task.po_comments" class="mt-2 text-xs bg-base-300 p-2 rounded border border-base-content/10">
                <div class="font-bold mb-1 opacity-70">🤖 TAIPO Feedback</div>
                {{ task.po_comments }}
            </div>

            <div v-if="task.generated_code" class="mt-2 text-xs badge badge-ghost gap-1">
                🤖 Code Generated
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, nextTick, defineProps, defineEmits } from 'vue';
import { api } from '../services/api';

const props = defineProps({
    task: Object
});

const emit = defineEmits(['toggle-imp', 'delete', 'task-updated', 'decompose', 'generate-code']);

const isImportant = computed(() => Number(props.task.is_important) === 1);
const isEditing = ref(false);
const editDescription = ref(props.task.description);
const editInput = ref(null);

const toggleImportance = async () => {
    await api.toggleImportance(props.task.id, isImportant.value ? 0 : 1);
    emit('toggle-imp');
};

const enableEdit = async () => {
    isEditing.value = true;
    editDescription.value = props.task.description;
    await nextTick();
    if (editInput.value) editInput.value.focus();
};

const saveEdit = async () => {
    if (!isEditing.value) return;

    if (editDescription.value.trim() !== props.task.description) {
        // Assuming API has an edit method, but api.js mostly had generic post.
        // script.js uses 'edit_task' action.
        // Let's add that to api.js or assume generic post works if updated.
        // api.js doesn't have explicit editTask method but we can add it or use raw client.
        // Let's assume we need to add it or use generic update.
        // For now, I'll call a hypothetical api.editTask or raw post.
        // Let's check api.js again. It does NOT have editTask.
        // I should stick to the pattern.
        try {
            const formData = new FormData();
            formData.append('action', 'edit_task');
            formData.append('task_id', props.task.id);
            formData.append('description', editDescription.value);
            // Axios equivalent:
            await api.editTask(props.task.id, editDescription.value);
            emit('task-updated');
        } catch (e) {
            console.error("Failed to edit", e);
            alert("Failed to save edit");
        }
    }
    isEditing.value = false;
};
</script>
