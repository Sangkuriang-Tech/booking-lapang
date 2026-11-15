<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import { formatRupiah } from '@/Utils/format';

const props = defineProps({
    allowsOts: {
        type: Boolean,
        required: true,
    },
    dpPercent: {
        type: Number,
        required: true,
    },
    timezone: {
        type: String,
        required: true,
    },
    selectedDate: {
        type: String,
        required: true,
    },
    selectedCourtUlid: {
        type: String,
        default: null,
    },
    courts: {
        type: Array,
        default: () => [],
    },
    slots: {
        type: Array,
        default: () => [],
    },
});

const filterCourt = ref(props.selectedCourtUlid || '');
const filterDate = ref(props.selectedDate);
const selectedStarts = ref([]);

const form = useForm({
    court_ulid: props.selectedCourtUlid || '',
    starts_at: '',
    ends_at: '',
    customer_name: '',
    customer_phone: '',
    payment_mode: 'full',
    payment_method: 'cash',
    direct_check_in: false,
    note: '',
});

function reloadSlots() {
    selectedStarts.value = [];
    router.get('/dashboard/ots', {
        court: filterCourt.value,
        date: filterDate.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function toggleSlot(slot) {
    if (!slot.available) {
        return;
    }

    const idx = selectedStarts.value.indexOf(slot.starts_at);
    if (idx !== -1) {
        selectedStarts.value.splice(idx, 1);
    } else {
        selectedStarts.value.push(slot.starts_at);
    }
    selectedStarts.value.sort();
}

const selectedSlotObjects = computed(() =>
    props.slots.filter((s) => selectedStarts.value.includes(s.starts_at)),
);

const totalEstimate = computed(() =>
    selectedSlotObjects.value.reduce((sum, s) => sum + s.price_per_hour, 0),
);

function submitOts() {
    if (selectedSlotObjects.value.length === 0) {
        return;
    }

    const sorted = [...selectedSlotObjects.value].sort((a, b) => a.starts_at.localeCompare(b.starts_at));
    form.court_ulid = filterCourt.value;
    form.starts_at = sorted[0].starts_at;
    form.ends_at = sorted[sorted.length - 1].ends_at;

    form.post('/dashboard/ots', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Booking OTS">
        <meta name="robots" content="noindex" />
    </Head>

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-3xl font-bold text-ink">
                    Booking On The Spot (OTS)
                </h1>
                <p class="mt-1 text-sm text-ink-muted">
                    Buat booking langsung untuk pelanggan yang datang ke lokasi.
                </p>
            </div>

            <div
                v-if="!allowsOts"
                role="alert"
                class="rounded-[var(--radius-card)] border border-danger bg-surface p-4 text-sm font-semibold text-danger"
            >
                Langganan venue sudah melewati masa tenggang (H+7). Pembuatan booking OTS dikunci sampai paket diperpanjang.
            </div>

            <section class="rounded-[var(--radius-card)] border border-border bg-surface p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="ots-court" class="block text-sm font-semibold text-ink">Lapangan</label>
                        <select
                            id="ots-court"
                            v-model="filterCourt"
                            class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                            @change="reloadSlots"
                        >
                            <option v-for="court in courts" :key="court.ulid" :value="court.ulid">
                                {{ court.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="ots-date" class="block text-sm font-semibold text-ink">Tanggal Main</label>
                        <input
                            id="ots-date"
                            v-model="filterDate"
                            type="date"
                            class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                            @change="reloadSlots"
                        />
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-sm font-semibold text-ink">Pilih Slot Jam</p>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4 md:grid-cols-6">
                        <button
                            v-for="slot in slots"
                            :key="slot.starts_at"
                            type="button"
                            :disabled="!slot.available"
                            :aria-pressed="selectedStarts.includes(slot.starts_at)"
                            :class="[
                                !slot.available
                                    ? 'cursor-not-allowed border-border bg-canvas text-ink-muted opacity-60'
                                    : selectedStarts.includes(slot.starts_at)
                                        ? 'border-primary bg-primary text-white'
                                        : 'border-border bg-surface text-ink hover:border-primary',
                            ]"
                            class="min-h-[44px] rounded-[var(--radius-control)] border px-3 py-2 text-left text-xs font-semibold"
                            @click="toggleSlot(slot)"
                        >
                            <div>{{ slot.label }}</div>
                            <div class="mt-0.5 font-normal opacity-80">{{ formatRupiah(slot.price_per_hour) }}</div>
                        </button>
                    </div>
                    <p v-if="form.errors.slot" class="mt-2 text-xs text-danger">{{ form.errors.slot }}</p>
                </div>

                <form class="mt-6 space-y-4 border-t border-border pt-6" @submit.prevent="submitOts">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="ots-name" class="block text-sm font-semibold text-ink">Nama Pelanggan</label>
                            <input
                                id="ots-name"
                                v-model="form.customer_name"
                                type="text"
                                required
                                maxlength="100"
                                class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                            />
                            <p v-if="form.errors.customer_name" class="mt-1 text-xs text-danger">{{ form.errors.customer_name }}</p>
                        </div>

                        <div>
                            <label for="ots-phone" class="block text-sm font-semibold text-ink">Nomor Telepon / WhatsApp</label>
                            <input
                                id="ots-phone"
                                v-model="form.customer_phone"
                                type="tel"
                                required
                                placeholder="081234567890"
                                class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                            />
                            <p v-if="form.errors.customer_phone" class="mt-1 text-xs text-danger">{{ form.errors.customer_phone }}</p>
                        </div>

                        <div>
                            <label for="ots-mode" class="block text-sm font-semibold text-ink">Jenis Pembayaran</label>
                            <select
                                id="ots-mode"
                                v-model="form.payment_mode"
                                class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                            >
                                <option value="full">Lunas ({{ formatRupiah(totalEstimate) }})</option>
                                <option value="dp">DP {{ dpPercent }}%</option>
                            </select>
                        </div>

                        <div>
                            <label for="ots-method" class="block text-sm font-semibold text-ink">Metode Pembayaran</label>
                            <select
                                id="ots-method"
                                v-model="form.payment_method"
                                class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                            >
                                <option value="cash">Tunai</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="qris">QRIS</option>
                            </select>
                        </div>
                    </div>

                    <label class="flex min-h-[44px] cursor-pointer items-center gap-2 text-sm font-medium text-ink">
                        <input
                            v-model="form.direct_check_in"
                            type="checkbox"
                            class="h-4 w-4 accent-primary"
                        />
                        <span>Langsung tandai Check-in (pelanggan langsung masuk lapangan sekarang)</span>
                    </label>

                    <Button
                        type="submit"
                        variant="primary"
                        :disabled="!allowsOts || form.processing || selectedSlotObjects.length === 0"
                    >
                        Buat Booking OTS
                    </Button>
                </form>
            </section>
        </div>
    </DashboardLayout>
</template>
