<script setup>
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    id: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    placeholder: {
        type: String,
        default: '',
    },
    inputmode: {
        type: String,
        default: undefined,
    },
    autocomplete: {
        type: String,
        default: undefined,
    },
    hint: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'blur']);

const generatedId = useId();
const inputId = computed(() => props.id || `input-${generatedId}`);
const errorId = computed(() => `${inputId.value}-error`);
const hintId = computed(() => `${inputId.value}-hint`);

const describedBy = computed(() => {
    const ids = [];
    if (props.error) {
        ids.push(errorId.value);
    } else if (props.hint) {
        ids.push(hintId.value);
    }
    return ids.length > 0 ? ids.join(' ') : undefined;
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            v-if="label"
            :for="inputId"
            class="text-sm font-semibold text-ink"
        >
            {{ label }}
            <span v-if="required" class="text-danger" aria-hidden="true">*</span>
        </label>

        <input
            :id="inputId"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :inputmode="inputmode"
            :autocomplete="autocomplete"
            :required="required"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            class="min-h-[44px] w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-muted transition-colors duration-150 focus:outline-none disabled:cursor-not-allowed disabled:bg-canvas disabled:opacity-70"
            :class="
                error
                    ? 'border-danger focus:ring-2 focus:ring-danger/25'
                    : 'border-border focus:border-primary focus:ring-2 focus:ring-primary/20'
            "
            @input="emit('update:modelValue', $event.target.value)"
            @blur="emit('blur', $event)"
        />

        <p
            v-if="error"
            :id="errorId"
            class="flex items-start gap-1.5 text-xs font-medium text-danger"
            role="alert"
        >
            <svg
                class="mt-0.5 h-3.5 w-3.5 shrink-0"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm-.75-4.75a.75.75 0 001.5 0v-4.5a.75.75 0 00-1.5 0v4.5zm.75-7a1 1 0 100 2 1 1 0 000-2z"
                    clip-rule="evenodd"
                />
            </svg>
            <span>{{ error }}</span>
        </p>

        <p
            v-else-if="hint"
            :id="hintId"
            class="text-xs text-ink-muted"
        >
            {{ hint }}
        </p>
    </div>
</template>
