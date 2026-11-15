<script setup>
defineProps({
    padded: {
        type: Boolean,
        default: true,
    },
    as: {
        type: String,
        default: 'div',
    },
});
</script>

<template>
    <component
        :is="as"
        class="rounded-[var(--radius-card)] border border-border bg-surface shadow-[0_1px_2px_rgba(14,27,22,0.04)]"
    >
        <div
            v-if="$slots.header"
            class="border-b border-border px-5 py-4"
        >
            <slot name="header" />
        </div>

        <div :class="padded ? 'p-5' : ''">
            <slot />
        </div>

        <div
            v-if="$slots.footer"
            class="border-t border-border px-5 py-3.5"
        >
            <slot name="footer" />
        </div>
    </component>
</template>
