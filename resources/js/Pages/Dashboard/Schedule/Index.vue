<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BookingStatusBadge from '@/Components/Booking/BookingStatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import { formatTimeRange } from '@/Utils/format';

const props = defineProps({
    selectedDate: {
        type: String,
        required: true,
    },
    timezone: {
        type: String,
        required: true,
    },
    courts: {
        type: Array,
        default: () => [],
    },
    blocks: {
        type: Array,
        default: () => [],
    },
    bookings: {
        type: Array,
        default: () => [],
    },
});

const dateFilter = ref(props.selectedDate);

const blockForm = useForm({
    court_ulid: props.courts[0]?.ulid || '',
    starts_at: `${props.selectedDate}T08:00`,
    ends_at: `${props.selectedDate}T10:00`,
    reason: '',
});

const deleteForm = useForm({});

function changeDate() {
    router.get('/dashboard/schedule', { date: dateFilter.value }, { preserveScroll: true });
}

function submitBlock() {
    blockForm.post('/dashboard/schedule/blocks', {
        preserveScroll: true,
        onSuccess: () => {
            blockForm.reason = '';
        },
    });
}

function removeBlock(id) {
    deleteForm.delete(`/dashboard/schedule/blocks/${id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Jadwal & Blokir Slot">
        <meta name="robots" content="noindex" />
    </Head>

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold text-ink">
                        Jadwal &amp; Blokir Slot
                    </h1>
                    <p class="mt-1 text-sm text-ink-muted">
                        Pantau jadwal harian dan blokir slot lapangan untuk perawatan atau turnamen.
                    </p>
                </div>

                <div>
                    <label for="schedule-date" class="block text-xs font-semibold text-ink-muted">Tanggal</label>
                    <input
                        id="schedule-date"
                        v-model="dateFilter"
                        type="date"
                        class="mt-1 min-h-[44px] rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                        @change="changeDate"
                    />
                </div>
            </div>

            <section class="rounded-[var(--radius-card)] border border-border bg-surface p-6">
                <h2 class="font-display text-xl font-bold text-ink">Buat Blokir Jadwal</h2>
                <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="submitBlock">
                    <div>
                        <label for="block-court" class="block text-sm font-semibold text-ink">Lapangan</label>
                        <select
                            id="block-court"
                            v-model="blockForm.court_ulid"
                            class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                        >
                            <option v-for="court in courts" :key="court.ulid" :value="court.ulid">
                                {{ court.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="block-reason" class="block text-sm font-semibold text-ink">Alasan Blokir</label>
                        <input
                            id="block-reason"
                            v-model="blockForm.reason"
                            type="text"
                            required
                            placeholder="Contoh: Perawatan rumput sintetis"
                            class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                        />
                        <p v-if="blockForm.errors.reason" class="mt-1 text-xs text-danger">{{ blockForm.errors.reason }}</p>
                    </div>

                    <div>
                        <label for="block-start" class="block text-sm font-semibold text-ink">Mulai</label>
                        <input
                            id="block-start"
                            v-model="blockForm.starts_at"
                            type="datetime-local"
                            required
                            class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                        />
                    </div>

                    <div>
                        <label for="block-end" class="block text-sm font-semibold text-ink">Selesai</label>
                        <input
                            id="block-end"
                            v-model="blockForm.ends_at"
                            type="datetime-local"
                            required
                            class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <p v-if="blockForm.errors.slot" class="mb-2 text-xs font-semibold text-danger">{{ blockForm.errors.slot }}</p>
                        <Button type="submit" variant="primary" :disabled="blockForm.processing">
                            Blokir Slot
                        </Button>
                    </div>
                </form>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-[var(--radius-card)] border border-border bg-surface p-6">
                    <h2 class="font-display text-xl font-bold text-ink">Daftar Blokir Jadwal</h2>
                    <div v-if="blocks.length === 0" class="mt-4 text-sm text-ink-muted">
                        Tidak ada blokir jadwal pada tanggal ini.
                    </div>
                    <div v-else class="mt-4 space-y-3">
                        <div
                            v-for="block in blocks"
                            :key="block.id"
                            class="flex items-center justify-between gap-3 rounded-[var(--radius-control)] border border-border p-3"
                        >
                            <div>
                                <p class="text-sm font-bold text-ink">{{ block.court_name }}</p>
                                <p class="text-xs text-ink-muted">
                                    {{ formatTimeRange(block.starts_at, block.ends_at, timezone) }} &middot; {{ block.reason }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="danger"
                                :disabled="deleteForm.processing"
                                @click="removeBlock(block.id)"
                            >
                                Hapus
                            </Button>
                        </div>
                    </div>
                </section>

                <section class="rounded-[var(--radius-card)] border border-border bg-surface p-6">
                    <h2 class="font-display text-xl font-bold text-ink">Booking Aktif</h2>
                    <div v-if="bookings.length === 0" class="mt-4 text-sm text-ink-muted">
                        Tidak ada booking aktif pada tanggal ini.
                    </div>
                    <div v-else class="mt-4 space-y-3">
                        <div
                            v-for="booking in bookings"
                            :key="booking.ulid"
                            class="flex items-center justify-between gap-3 rounded-[var(--radius-control)] border border-border p-3"
                        >
                            <div>
                                <p class="text-sm font-bold text-ink">#{{ booking.code }} — {{ booking.customer_name }}</p>
                                <p class="text-xs text-ink-muted">
                                    {{ booking.court_name }} &middot; {{ formatTimeRange(booking.starts_at, booking.ends_at, timezone) }}
                                </p>
                            </div>
                            <BookingStatusBadge :status="booking.status" />
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </DashboardLayout>
</template>
