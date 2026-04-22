<template>
    <dialog
        ref="modalRef"
        class="modal modal-bottom sm:modal-middle"
        :class="{ 'modal-open': isOpen }"
    >
        <div class="modal-box max-w-2xl bg-base-100 border border-base-300 shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-primary">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    Project Requirements
                </h3>
                <button @click="close" class="btn btn-sm btn-circle btn-ghost">✕</button>
            </div>

            <div class="form-control gap-4">
                <div class="flex justify-between items-end">
                    <label for="requirement-textarea" class="label p-0 cursor-pointer">
                        <span class="label-text font-semibold opacity-70 uppercase text-xs tracking-widest">Requirements (Text/Markdown)</span>
                    </label>
                    <div class="flex gap-2">
                        <input
                            @change="handleFileUpload"
                            type="file"
                            id="file-upload"
                            class="hidden"
                            accept=".txt,.md"
                        >
                        <label
                            for="file-upload"
                            class="btn btn-xs btn-outline btn-secondary gap-1"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            Import File
                        </label>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <textarea
                        id="requirement-textarea"
                        v-model="content"
                        class="textarea textarea-bordered h-64 font-mono text-sm focus:textarea-primary transition-colors w-full"
                        placeholder="Enter project requirements here (Markdown supported). These will be used as context for AI code generation and task decomposition..."
                    ></textarea>
                </div>

                <div v-if="message" class="alert shadow-sm py-2" :class="messageType === 'error' ? 'alert-error' : 'alert-success'">
                    <svg v-if="messageType === 'success'" xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-4 w-4" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-4 w-4" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span class="text-xs">{{ message }}</span>
                </div>
            </div>

            <div class="modal-action flex justify-between items-center mt-8">
                <p class="text-[10px] opacity-40 italic max-w-[60%] leading-tight">
                    * Requirements are shared across the team and used to guide AI suggestions.
                </p>
                <div class="flex gap-2">
                    <button @click="close" class="btn btn-ghost">Dismiss</button>
                    <button
                        @click="save"
                        :disabled="isSaving || !content.trim()"
                        class="btn btn-primary px-8 shadow-lg"
                    >
                        <span v-if="isSaving" class="loading loading-spinner loading-xs"></span>
                        {{ isSaving ? 'Saving' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop" @click="close">
            <button>close</button>
        </form>
    </dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import { api } from '../services/api';

const props = defineProps({
    isOpen: Boolean,
    projectName: String
});

const emit = defineEmits(['close']);
const modalRef = ref(null);

const content = ref('');
const isSaving = ref(false);
const message = ref('');
const messageType = ref('');

const close = () => {
    emit('close');
    message.value = '';
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 1024 * 1024) {
        message.value = "File is too large (max 1MB).";
        messageType.value = "error";
        return;
    }

    file.text().then(text => {
        content.value = text;
        message.value = `Loaded ${file.name} successfully.`;
        messageType.value = "success";

        setTimeout(() => {
            if (messageType.value === 'success') message.value = '';
        }, 3000);
    }).catch(() => {
        message.value = "Failed to read file.";
        messageType.value = "error";
    });

    event.target.value = '';
};

const loadRequirements = async () => {
    if (!props.projectName) return;

    try {
        const result = await api.getRequirements(props.projectName);
        if (result.success && result.data && result.data.length > 0) {
            content.value = result.data[0].content;
        } else {
            content.value = '';
        }
    } catch (error) {
        console.error("Failed to load requirements", error);
    }
};

watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        loadRequirements();
    }
});

const save = async () => {
    if (!content.value.trim()) {
        message.value = "Content cannot be empty.";
        messageType.value = "error";
        return;
    }

    isSaving.value = true;
    message.value = '';

    try {
        const result = await api.saveRequirement(props.projectName, content.value);
        if (result.data.success) {
            message.value = 'Requirements saved successfully!';
            messageType.value = 'success';
            setTimeout(() => {
                close();
            }, 1000);
        } else {
            message.value = 'Error saving requirements: ' + (result.data.error || 'Unknown error');
            messageType.value = 'error';
        }
    } catch (error) {
        message.value = 'Error saving requirements: ' + error.message;
        messageType.value = 'error';
    } finally {
        isSaving.value = false;
    }
};
</script>

<style scoped>
/* No manual styles needed, using DaisyUI classes */
</style>
