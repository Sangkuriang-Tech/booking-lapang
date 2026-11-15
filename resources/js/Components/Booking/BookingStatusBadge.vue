<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const meta = computed(() => {
    switch (props.status) {
        case 'held':
            return { label: 'Menunggu Pembayaran', tone: 'border-warning bg-canvas text-warning', icon: 'clock' };
        case 'awaiting_validation':
            return { label: 'Menunggu Validasi', tone: 'border-primary bg-canvas text-primary', icon: 'hourglass' };
        case 'awaiting_reupload':
            return { label: 'Perlu Bukti Baru', tone: 'border-warning bg-canvas text-warning', icon: 'alert' };
        case 'confirmed':
            return { label: 'Dikonfirmasi', tone: 'border-success bg-canvas text-success', icon: 'check' };
        case 'checked_in':
            return { label: 'Check-In', tone: 'border-success bg-canvas text-success', icon: 'check' };
        case 'completed':
            return { label: 'Selesai', tone: 'border-border bg-canvas text-ink', icon: 'check' };
        case 'rejected':
            return { label: 'Ditolak', tone: 'border-danger bg-canvas text-danger', icon: 'x' };
        case 'expired':
            return { label: 'Kedaluwarsa', tone: 'border-border bg-canvas text-ink-muted', icon: 'clock' };
        case 'cancelled':
            return { label: 'Dibatalkan', tone: 'border-border bg-canvas text-ink-muted', icon: 'x' };
        case 'no_show':
            return { label: 'Tidak Hadir', tone: 'border-danger bg-canvas text-danger', icon: 'x' };
        default:
            return { label: props.status, tone: 'border-border bg-canvas text-ink-muted', icon: 'clock' };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-[var(--radius-control)] border px-2.5 py-1 text-xs font-semibold"
        :class="meta.tone"
    >
        <svg
            v-if="meta.icon === 'check'"
            class="h-3.5 w-3.5 shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                fill-rule="evenodd"
                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                clip-rule="evenodd"
            />
        </svg>
        <svg
            v-else-if="meta.icon === 'x'"
            class="h-3.5 w-3.5 shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
            />
        </svg>
        <svg
            v-else-if="meta.icon === 'alert'"
            class="h-3.5 w-3.5 shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                fill-rule="evenodd"
                d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                clip-rule="evenodd"
            />
        </svg>
        <svg
            v-else
            class="h-3.5 w-3.5 shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z"
                clip-rule="evenodd"
            />
        </svg>
        <span>{{ meta.label }}</span>
    </span>
</template>
