<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    confirmLabel: {
        type: String,
        default: 'Ya, Lanjutkan',
    },
    cancelLabel: {
        type: String,
        default: 'Batal',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['confirm', 'close']);

const dialogRef = ref(null);
const previousFocus = ref(null);

function handleKeydown(event) {
    if (!props.open) {
        return;
    }

    if (event.key === 'Escape' && !props.loading) {
        emit('close');
        return;
    }

    if (event.key === 'Tab' && dialogRef.value) {
        const focusable = dialogRef.value.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        );
        if (focusable.length === 0) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            previousFocus.value = document.activeElement;
            window.addEventListener('keydown', handleKeydown);
            await nextTick();
            const firstButton = dialogRef.value?.querySelector('button');
            firstButton?.focus();
        } else {
            window.removeEventListener('keydown', handleKeydown);
            previousFocus.value?.focus?.();
        }
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        @click.self="!loading && emit('close')"
    >
        <div
            ref="dialogRef"
            class="w-full max-w-md rounded-[var(--radius-card)] border border-border bg-surface p-6 shadow-lg"
        >
            <h2 id="confirm-dialog-title" class="font-display text-2xl font-bold text-ink">
                {{ title }}
            </h2>
            <p class="mt-2 text-sm text-ink-muted">
                {{ description }}
            </p>
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <Button
                    variant="secondary"
                    :disabled="loading"
                    @click="emit('close')"
                >
                    {{ cancelLabel }}
                </Button>
                <Button
                    variant="danger"
                    :loading="loading"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </Button>
            </div>
        </div>
    </div>
</template>
