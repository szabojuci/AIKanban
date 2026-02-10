<template>
    <div
        :class="{ 'modal-open': isOpen }"
        class="modal modal-bottom sm:modal-middle"
    >
        <div class="modal-box relative">
            <button
                @click="$emit('close')"
                class="btn btn-sm btn-circle absolute right-2 top-2"
            >
                ✕
            </button>
            <h3 class="font-bold text-lg">Generated Code</h3>

            <div
                v-if="loading"
                class="flex flex-col items-center justify-center p-8"
            >
                <span class="loading loading-spinner loading-lg text-primary"></span>
                <p class="mt-4 text-sm opacity-70">AI is writing your code...</p>
            </div>

            <div
                v-else-if="error"
                class="alert alert-error mt-4"
            >
                <p class="font-bold">{{ error.split(' - Response:')[0] }}</p>
                <div v-if="error.includes(' - Response:')" class="mt-2 text-xs opacity-75 font-mono break-all bg-black/10 p-2 rounded">
                    {{ error.split(' - Response:')[1] }}
                </div>
            </div>

            <div v-else class="mt-4">
                <div class="mockup-code bg-neutral text-neutral-content max-h-[60vh] overflow-y-auto">
                    <pre><code>{{ code }}</code></pre>
                </div>
            </div>

            <div class="modal-action">
                <button
                    @click="$emit('close')"
                    class="btn"
                >Close</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button
                @click="$emit('close')"
            >close</button>
        </form>
    </div>
</template>

<script setup>
const props = defineProps({
    isOpen: Boolean,
    loading: Boolean,
    code: String,
    error: String,
});
</script>
