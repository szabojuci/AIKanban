<template>
    <div
        class="card bg-base-200 shadow-sm hover:shadow-md transition-all duration-200 cursor-move border-l-4"
        :class="{'border-warning': isImportant, 'border-base-300': !isImportant}"
        draggable="true"
    >
        <div class="card-body p-3">
            <div class="flex justify-between items-start mb-2">

                <button
                    class="btn btn-ghost btn-xs btn-circle"
                    @click="toggleImportance"
                    :class="{'text-warning': isImportant, 'text-base-content/20': !isImportant}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                </button>

                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-xs btn-circle text-base-content/50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="w-5 h-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
                    </div>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                        <li><a @click="$emit('delete')">Delete</a></li>
                    </ul>
                </div>
            </div>

            <p class="text-sm">{{ task.description }}</p>

            <div v-if="task.generated_code" class="mt-2 text-xs badge badge-neutral">
                🤖 Code Generated
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, defineProps, defineEmits } from 'vue';
import { api } from '../services/api';

const props = defineProps({
    task: Object
});

const emit = defineEmits(['toggle-imp', 'delete']);

const isImportant = computed(() => Number(props.task.is_important) === 1);

const toggleImportance = async () => {
    await api.toggleImportance(props.task.id, isImportant.value ? 0 : 1);
    emit('toggle-imp');
};
</script>
