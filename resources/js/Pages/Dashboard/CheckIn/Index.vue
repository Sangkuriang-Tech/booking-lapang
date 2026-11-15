<script setup>
import { computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BookingStatusBadge from '@/Components/Booking/BookingStatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import { formatDate, formatPhone, formatRupiah, formatTimeRange } from '@/Utils/format';

const props = defineProps({
    todayBookings: {
        type: Array,
        default: () => [],
    },
    scannedBooking: {
        type: Object,
        default: null,
    },
});

const scanForm = useForm({
    token: '',
});

const checkInForm = useForm({
    token: '',
    settlement_method: 'cash',
    settlement_amount: 0,
    note: '',
});

const completeForm = useForm({});

watch(
    () => props.scannedBooking,
    (booking) => {
        if (booking) {
            checkInForm.token = scanForm.token;
            checkInForm.settlement_amount = booking.remaining_amount;
            checkInForm.settlement_method = 'cash';
            checkInForm.note = '';
        }
    },
    { immediate: true },
);

const needsSettlement = computed(() => (props.scannedBooking?.remaining_amount || 0) > 0);

function lookupQr() {
    scanForm.post('/dashboard/check-in/lookup', {
        preserveScroll: true,
    });
}

function submitCheckIn() {
    checkInForm.token = scanForm.token;
    checkInForm.post('/dashboard/check-in', {
        preserveScroll: true,
        onSuccess: () => {
            scanForm.reset();
            checkInForm.reset();
        },
    });
}

function markCompleted(booking) {
    completeForm.post(`/dashboard/check-in/${booking.ulid}/complete`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Scan QR & Check-in">
        <meta name="robots" content="noindex" />
    </Head>

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-3xl font-bold text-ink">
                    Scan QR &amp; Check-in Venue
                </h1>
                <p class="mt-1 text-sm text-ink-muted">
                    Pindai atau masukkan token QR pelanggan, verifikasi detail transaksi, lalu catat pelunasan jika status masih DP.
                </p>
            </div>

            <section class="rounded-[var(--radius-card)] border border-border bg-surface p-6">
                <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="lookupQr">
                    <div class="flex-1">
                        <label for="qr-token-input" class="block text-sm font-semibold text-ink">
                            Token QR Tiket
                        </label>
                        <input
                            id="qr-token-input"
                            v-model="scanForm.token"
                            type="text"
                            required
                            autocomplete="off"
                            :aria-invalid="Boolean(scanForm.errors.token)"
                            aria-describedby="qr-token-error"
                            class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3.5 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                            placeholder="Arahkan scanner QR ke kolom ini atau tempel token..."
                        />
                        <p v-if="scanForm.errors.token" id="qr-token-error" class="mt-1 text-xs text-danger">
                            {{ scanForm.errors.token }}
                        </p>
                    </div>

                    <Button type="submit" variant="primary" :disabled="scanForm.processing">
                        Verifikasi QR
                    </Button>
                </form>

                <div
                    v-if="scannedBooking"
                    class="mt-6 rounded-[var(--radius-card)] border border-primary bg-canvas p-5"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
                        <h2 class="font-display text-xl font-bold text-ink">
                            Detail Tiket #{{ scannedBooking.code }}
                        </h2>
                        <BookingStatusBadge :status="scannedBooking.status" />
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-ink-muted">Nama Pembooking</dt>
                            <dd class="font-semibold text-ink">{{ scannedBooking.customer_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-muted">Nomor Telepon</dt>
                            <dd class="font-semibold text-ink">{{ formatPhone(scannedBooking.customer_phone) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-muted">Lapangan</dt>
                            <dd class="font-semibold text-ink">{{ scannedBooking.court_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-muted">Jadwal Main</dt>
                            <dd class="font-semibold text-ink">
                                {{ formatDate(scannedBooking.starts_at, scannedBooking.timezone) }},
                                {{ formatTimeRange(scannedBooking.starts_at, scannedBooking.ends_at, scannedBooking.timezone) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-muted">Total Tarif / Sudah Dibayar</dt>
                            <dd class="font-semibold text-ink">
                                {{ formatRupiah(scannedBooking.price_total) }} / {{ formatRupiah(scannedBooking.paid_total) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-muted">Status Pembayaran</dt>
                            <dd
                                :class="needsSettlement ? 'text-warning' : 'text-success'"
                                class="font-bold"
                            >
                                {{ needsSettlement ? `Belum Lunas (Sisa ${formatRupiah(scannedBooking.remaining_amount)})` : 'Lunas' }}
                            </dd>
                        </div>
                    </dl>

                    <form class="mt-5 space-y-4 border-t border-border pt-4" @submit.prevent="submitCheckIn">
                        <div v-if="needsSettlement" class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="settlement-method" class="block text-sm font-semibold text-ink">
                                    Metode Pelunasan di Tempat
                                </label>
                                <select
                                    id="settlement-method"
                                    v-model="checkInForm.settlement_method"
                                    class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                >
                                    <option value="cash">Tunai</option>
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="qris">QRIS</option>
                                </select>
                                <p v-if="checkInForm.errors.settlement_method" class="mt-1 text-xs text-danger">
                                    {{ checkInForm.errors.settlement_method }}
                                </p>
                            </div>

                            <div>
                                <label for="settlement-note" class="block text-sm font-semibold text-ink">
                                    Catatan Pelunasan (opsional)
                                </label>
                                <input
                                    id="settlement-note"
                                    v-model="checkInForm.note"
                                    type="text"
                                    class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                    placeholder="Opsional"
                                />
                            </div>
                        </div>

                        <p v-if="checkInForm.errors.token || checkInForm.errors.settlement_amount" class="text-xs text-danger">
                            {{ checkInForm.errors.token || checkInForm.errors.settlement_amount }}
                        </p>

                        <Button type="submit" variant="primary" :disabled="checkInForm.processing">
                            {{ needsSettlement ? `Catat Pelunasan ${formatRupiah(scannedBooking.remaining_amount)} & Check-in` : 'Konfirmasi Check-in' }}
                        </Button>
                    </form>
                </div>
            </section>

            <section class="rounded-[var(--radius-card)] border border-border bg-surface p-6">
                <h2 class="font-display text-xl font-bold text-ink">
                    Jadwal Hari Ini (Dikonfirmasi &amp; Check-in)
                </h2>

                <div v-if="todayBookings.length === 0" class="mt-4 text-sm text-ink-muted">
                    Belum ada booking terkonfirmasi atau sedang bermain hari ini.
                </div>

                <div v-else class="mt-4 space-y-3">
                    <div
                        v-for="item in todayBookings"
                        :key="item.ulid"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-control)] border border-border p-4"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-display text-lg font-bold text-ink">#{{ item.code }}</span>
                                <span class="text-sm font-semibold text-ink">{{ item.customer_name }}</span>
                                <BookingStatusBadge :status="item.status" />
                            </div>
                            <p class="mt-1 text-xs text-ink-muted">
                                {{ item.court_name }} &middot; {{ formatTimeRange(item.starts_at, item.ends_at, item.timezone) }}
                                &middot; {{ item.remaining_amount > 0 ? `Sisa DP: ${formatRupiah(item.remaining_amount)}` : 'Lunas' }}
                            </p>
                        </div>

                        <Button
                            v-if="item.status === 'checked_in'"
                            type="button"
                            variant="secondary"
                            :disabled="completeForm.processing"
                            @click="markCompleted(item)"
                        >
                            Tandai Selesai
                        </Button>
                    </div>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
