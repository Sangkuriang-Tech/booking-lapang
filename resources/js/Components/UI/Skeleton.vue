<script setup>
defineProps({
    variant: {
        type: String,
        default: 'text',
        validator: (val) => ['image', 'text', 'card', 'slot-grid', 'table-row'].includes(val),
    },
    lines: {
        type: Number,
        default: 3,
    },
    slotsCount: {
        type: Number,
        default: 6,
    },
    aspectClass: {
        type: String,
        default: 'aspect-[16/10]',
    },
});
</script>

<template>
    <div aria-hidden="true">
        <div
            v-if="variant === 'image'"
            class="skeleton-pulse w-full rounded-[var(--radius-card)]"
            :class="aspectClass"
        />

        <div
            v-else-if="variant === 'text'"
            class="flex flex-col gap-2.5"
        >
            <div
                v-for="index in lines"
                :key="index"
                class="skeleton-pulse h-4 rounded"
                :class="index === lines ? 'w-3/5' : 'w-full'"
            />
        </div>

        <div
            v-else-if="variant === 'card'"
            class="overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface p-4"
        >
            <div class="skeleton-pulse aspect-[16/10] w-full rounded-[var(--radius-control)]" />
            <div class="mt-4 flex flex-col gap-2.5">
                <div class="skeleton-pulse h-5 w-2/3 rounded" />
                <div class="skeleton-pulse h-4 w-1/2 rounded" />
                <div class="mt-1 flex items-center justify-between">
                    <div class="skeleton-pulse h-5 w-28 rounded" />
                    <div class="skeleton-pulse h-11 w-24 rounded-[var(--radius-control)]" />
                </div>
            </div>
        </div>

        <div
            v-else-if="variant === 'slot-grid'"
            class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4"
        >
            <div
                v-for="index in slotsCount"
                :key="index"
                class="skeleton-pulse h-12 rounded-[var(--radius-control)]"
            />
        </div>

        <div
            v-else-if="variant === 'table-row'"
            class="flex items-center gap-4 border-b border-border py-3.5"
        >
            <div class="skeleton-pulse h-4 w-24 rounded" />
            <div class="skeleton-pulse h-4 flex-1 rounded" />
            <div class="skeleton-pulse h-4 w-28 rounded" />
            <div class="skeleton-pulse h-8 w-20 rounded-[var(--radius-control)]" />
        </div>
    </div>
</template>
