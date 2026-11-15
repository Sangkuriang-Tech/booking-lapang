<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: String,
        default: 'button',
    },
    variant: {
        type: String,
        default: 'primary',
        validator: (val) => ['primary', 'secondary', 'outline', 'danger', 'accent'].includes(val),
    },
    size: {
        type: String,
        default: 'md',
        validator: (val) => ['md', 'lg'].includes(val),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    fullWidth: {
        type: Boolean,
        default: false,
    },
});

const isDisabled = computed(() => props.disabled || props.loading);

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'secondary':
            return 'bg-canvas text-ink border border-border hover:bg-[#EAE8E0]';
        case 'outline':
            return 'bg-surface text-primary border border-primary hover:bg-[#F0F4F2]';
        case 'danger':
            return 'bg-danger text-white border border-danger hover:bg-[#961F18]';
        case 'accent':
            return 'bg-accent text-ink border border-ink/15 hover:bg-[#C7E63E]';
        case 'primary':
        default:
            return 'bg-primary text-white border border-primary hover:bg-primary-hover';
    }
});

const sizeClasses = computed(() =>
    props.size === 'lg' ? 'min-h-[48px] px-6 py-3 text-base' : 'min-h-[44px] px-4 py-2.5 text-sm',
);
</script>

<template>
    <button
        :type="type"
        :disabled="isDisabled"
        :aria-busy="loading"
        class="inline-flex items-center justify-center gap-2 rounded-[var(--radius-control)] font-semibold transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-60"
        :class="[variantClasses, sizeClasses, fullWidth ? 'w-full' : '']"
    >
        <svg
            v-if="loading"
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="3"
            />
            <path
                class="opacity-90"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"
            />
        </svg>
        <slot />
    </button>
</template>
