<script setup>
import { computed, ref, watch } from 'vue';
import Skeleton from '@/Components/UI/Skeleton.vue';
import { formatRupiah } from '@/Utils/format';

const props = defineProps({
    slots: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:selection']);

const anchorIndex = ref(null);
const endIndex = ref(null);
const rangeNotice = ref('');

watch(
    () => props.slots,
    () => {
        anchorIndex.value = null;
        endIndex.value = null;
        rangeNotice.value = '';
        emit('update:selection', null);
    },
);

const selectedIndices = computed(() => {
    if (anchorIndex.value === null || endIndex.value === null) {
        return new Set();
    }

    const from = Math.min(anchorIndex.value, endIndex.value);
    const to = Math.max(anchorIndex.value, endIndex.value);
    const set = new Set();
    for (let i = from; i <= to; i += 1) {
        set.add(i);
    }

    return set;
});

function selectSlot(index) {
    const slot = props.slots[index];
    if (!slot || !slot.is_available) {
        return;
    }

    rangeNotice.value = '';

    if (anchorIndex.value === null) {
        anchorIndex.value = index;
        endIndex.value = index;
        emitSelection();
        return;
    }

    if (anchorIndex.value === index && endIndex.value === index) {
        anchorIndex.value = null;
        endIndex.value = null;
        emit('update:selection', null);
        return;
    }

    const from = Math.min(anchorIndex.value, index);
    const to = Math.max(anchorIndex.value, index);

    let allAvailable = true;
    for (let i = from; i <= to; i += 1) {
        if (!props.slots[i]?.is_available) {
            allAvailable = false;
            break;
        }
    }

    if (!allAvailable) {
        anchorIndex.value = index;
        endIndex.value = index;
        rangeNotice.value = 'Pemilihan multi-jam harus berurutan tanpa melewati slot yang terisi.';
        emitSelection();
        return;
    }

    anchorIndex.value = from;
    endIndex.value = to;
    emitSelection();
}

function emitSelection() {
    if (anchorIndex.value === null || endIndex.value === null) {
        emit('update:selection', null);
        return;
    }

    const from = Math.min(anchorIndex.value, endIndex.value);
    const to = Math.max(anchorIndex.value, endIndex.value);
    const firstSlot = props.slots[from];
    const lastSlot = props.slots[to];

    if (!firstSlot || !lastSlot) {
        emit('update:selection', null);
        return;
    }

    let totalPrice = 0;
    for (let i = from; i <= to; i += 1) {
        totalPrice += Number(props.slots[i]?.price || 0);
    }

    emit('update:selection', {
        starts_at: firstSlot.starts_at,
        ends_at: lastSlot.ends_at,
        total_price: totalPrice,
        hours_count: to - from + 1,
        label: `${firstSlot.label.split('–')[0]}–${lastSlot.label.split('–')[1]}`,
    });
}

function slotStatusText(slot, index) {
    if (selectedIndices.value.has(index)) {
        return 'Dipilih';
    }
    if (slot.is_available) {
        return formatRupiah(slot.price);
    }
    switch (slot.unavailable_reason) {
        case 'booked':
            return 'Dipesan';
        case 'blocked':
            return 'Diblokir';
        case 'past':
            return 'Lewat';
        default:
            return 'Tutup';
    }
}
</script>

<template>
    <div>
        <Skeleton v-if="loading" variant="slot-grid" :slots-count="8" />

        <div
            v-else-if="slots.length === 0"
            class="rounded-[var(--radius-card)] border border-border bg-canvas p-5 text-sm text-ink-muted"
        >
            Venue tutup atau tidak ada jadwal tersedia pada tanggal yang dipilih. Silakan pilih tanggal atau lapangan lain.
        </div>

        <div v-else>
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4">
                <button
                    v-for="(slot, index) in slots"
                    :key="slot.starts_at"
                    type="button"
                    :disabled="!slot.is_available"
                    :aria-pressed="selectedIndices.has(index)"
                    :aria-disabled="!slot.is_available"
                    class="flex min-h-[56px] flex-col items-start justify-between rounded-[var(--radius-control)] border px-3 py-2.5 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    :class="[
                        selectedIndices.has(index)
                            ? 'border-primary bg-primary text-white'
                            : slot.is_available
                              ? 'border-border bg-surface text-ink hover:border-primary hover:bg-canvas'
                              : 'cursor-not-allowed border-border bg-canvas text-ink-muted opacity-70',
                    ]"
                    @click="selectSlot(index)"
                >
                    <span class="flex w-full items-center justify-between gap-1 text-sm font-bold">
                        <span>{{ slot.label }}</span>
                        <svg
                            v-if="selectedIndices.has(index)"
                            class="h-4 w-4 shrink-0 text-accent"
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
                            v-else-if="!slot.is_available"
                            class="h-4 w-4 shrink-0"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </span>
                    <span
                        class="mt-1 text-xs font-semibold"
                        :class="selectedIndices.has(index) ? 'text-accent' : slot.is_available ? 'text-primary' : 'text-ink-muted'"
                    >
                        {{ slotStatusText(slot, index) }}
                    </span>
                </button>
            </div>

            <p v-if="rangeNotice" role="status" class="mt-3 text-xs font-semibold text-warning">
                {{ rangeNotice }}
            </p>
        </div>
    </div>
</template>
