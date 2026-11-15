<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'info',
        validator: (val) => ['success', 'warning', 'error', 'info'].includes(val),
    },
    message: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const toneClasses = computed(() => {
    switch (props.type) {
        case 'success':
            return 'border-success bg-surface text-ink';
        case 'warning':
            return 'border-warning bg-surface text-ink';
        case 'error':
            return 'border-danger bg-surface text-ink';
        case 'info':
        default:
            return 'border-primary bg-surface text-ink';
    }
});

const iconColorClass = computed(() => {
    switch (props.type) {
        case 'success':
            return 'text-success';
        case 'warning':
            return 'text-warning';
        case 'error':
            return 'text-danger';
        case 'info':
        default:
            return 'text-primary';
    }
});
</script>

<template>
    <div
        aria-live="polite"
        aria-atomic="true"
        class="pointer-events-none fixed right-4 bottom-4 z-50 w-full max-w-sm"
    >
        <div
            v-if="show && message"
            role="status"
            class="pointer-events-auto flex items-center justify-between gap-3 rounded-[var(--radius-card)] border-l-4 border border-border px-4 py-3 shadow-[0_4px_12px_rgba(14,27,22,0.08)]"
            :class="toneClasses"
        >
            <div class="flex items-start gap-2.5">
                <svg
                    v-if="type === 'success'"
                    class="mt-0.5 h-5 w-5 shrink-0"
                    :class="iconColorClass"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                        clip-rule="evenodd"
                    />
                </svg>

                <svg
                    v-else-if="type === 'error' || type === 'warning'"
                    class="mt-0.5 h-5 w-5 shrink-0"
                    :class="iconColorClass"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fill-rule="evenodd"
                        d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z"
                        clip-rule="evenodd"
                    />
                </svg>

                <svg
                    v-else
                    class="mt-0.5 h-5 w-5 shrink-0"
                    :class="iconColorClass"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z"
                        clip-rule="evenodd"
                    />
                </svg>

                <p class="text-sm font-medium text-ink">
                    {{ message }}
                </p>
            </div>

            <button
                type="button"
                aria-label="Tutup notifikasi"
                class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-[var(--radius-control)] text-ink-muted hover:bg-canvas hover:text-ink"
                @click="emit('close')"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                    />
                </svg>
            </button>
        </div>
    </div>
</template>
