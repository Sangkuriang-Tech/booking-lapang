<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import BookingStatusBadge from '@/Components/Booking/BookingStatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue';
import { formatDate, formatPhone, formatRupiah, formatTime, formatTimeRange } from '@/Utils/format';

const props = defineProps({
    booking: {
        type: Object,
        required: true,
    },
    payment_accounts: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const toastState = ref({
    show: Boolean(page.props.flash?.success || page.props.flash?.error),
    type: page.props.flash?.error ? 'error' : 'success',
    message: page.props.flash?.error || page.props.flash?.success || '',
});

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toastState.value = { show: true, type: 'success', message: flash.success };
        } else if (flash?.error) {
            toastState.value = { show: true, type: 'error', message: flash.error };
        }
    },
);

// Countdown timer initialized only in onMounted to prevent SSR hydration mismatch.
const remainingSeconds = ref(null);
let countdownTimer = null;

function updateRemaining() {
    if (!props.booking.hold_expires_at) {
        remainingSeconds.value = null;
        return;
    }

    const diff = Math.floor((new Date(props.booking.hold_expires_at).getTime() - Date.now()) / 1000);
    remainingSeconds.value = Math.max(0, diff);
}

onMounted(() => {
    if (props.booking.hold_expires_at) {
        updateRemaining();
        countdownTimer = window.setInterval(updateRemaining, 1000);
    }
});

onBeforeUnmount(() => {
    if (countdownTimer !== null) {
        window.clearInterval(countdownTimer);
    }
});

const formattedCountdown = computed(() => {
    if (remainingSeconds.value === null) {
        return null;
    }
    const mins = Math.floor(remainingSeconds.value / 60);
    const secs = remainingSeconds.value % 60;

    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

const canUploadProof = computed(() =>
    ['held', 'awaiting_reupload'].includes(props.booking.status),
);

const canCancel = computed(() =>
    ['held', 'awaiting_validation', 'awaiting_reupload', 'confirmed'].includes(props.booking.status),
);

const payableAmount = computed(() =>
    props.booking.payment_mode === 'full' ? props.booking.price_total : props.booking.dp_required,
);

const proofForm = useForm({
    method: 'transfer',
    proof: null,
    note: '',
});

const clientFileError = ref('');

function onFileChange(event) {
    const file = event.target.files?.[0] || null;
    clientFileError.value = '';
    proofForm.clearErrors('proof');

    if (!file) {
        proofForm.proof = null;
        return;
    }

    if (!['image/jpeg', 'image/png'].includes(file.type)) {
        clientFileError.value = 'Bukti bayar hanya mendukung format JPG atau PNG.';
        proofForm.proof = null;
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        clientFileError.value = 'Bukti bayar maksimal 2 MB (JPG atau PNG).';
        proofForm.proof = null;
        return;
    }

    proofForm.proof = file;
}

function submitProof() {
    if (proofForm.processing) {
        return;
    }

    if (!proofForm.proof) {
        clientFileError.value = 'Silakan pilih file bukti pembayaran (JPG atau PNG).';
        return;
    }

    proofForm.post(`/bookings/${props.booking.ulid}/proof`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            proofForm.reset('proof', 'note');
        },
    });
}

const showCancelDialog = ref(false);
const cancelForm = useForm({
    reason: '',
});

function confirmCancel() {
    if (cancelForm.processing) {
        return;
    }

    cancelForm.post(`/bookings/${props.booking.ulid}/cancel`, {
        preserveScroll: true,
        onSuccess: () => {
            showCancelDialog.value = false;
        },
    });
}
</script>

<template>
    <Head>
        <title>Detail Pesanan #{{ booking.code }}</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout :toast="toastState" @close-toast="toastState.show = false">
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link
                        href="/bookings"
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        &larr; Kembali ke Riwayat Pesanan
                    </Link>
                    <h1 class="mt-1 font-display text-3xl font-bold text-ink">
                        Pesanan #{{ booking.code }}
                    </h1>
                </div>

                <BookingStatusBadge :status="booking.status" />
            </div>

            <div
                v-if="booking.hold_expires_at && canUploadProof"
                role="status"
                class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-card)] border border-warning bg-surface p-4"
            >
                <div>
                    <p class="text-sm font-bold text-ink">
                        {{ booking.status === 'awaiting_reupload' ? 'Batas Waktu Unggah Ulang Bukti' : 'Slot Ditahan Sementara' }}
                    </p>
                    <p class="text-xs text-ink-muted">
                        Selesaikan unggah bukti pembayaran sebelum pukul
                        {{ formatTime(booking.hold_expires_at, booking.venue.timezone) }}.
                    </p>
                </div>
                <div class="rounded-[var(--radius-control)] bg-canvas px-4 py-2 font-display text-2xl font-bold text-warning">
                    {{ formattedCountdown ?? '--:--' }}
                </div>
            </div>

            <div
                v-if="booking.status_reason"
                role="alert"
                class="mb-6 rounded-[var(--radius-card)] border border-border bg-surface p-4 text-sm text-ink"
            >
                <span class="font-bold">Catatan Status:</span> {{ booking.status_reason }}
            </div>

            <section
                v-if="booking.status === 'confirmed' && booking.qr_svg_data_uri"
                aria-label="Tiket QR Check-in"
                class="mb-6 flex flex-col items-center gap-4 rounded-[var(--radius-card)] border border-success bg-surface p-6 text-center sm:flex-row sm:text-left"
            >
                <div class="shrink-0 rounded-[var(--radius-control)] border border-border bg-white p-2">
                    <img
                        :src="booking.qr_svg_data_uri"
                        :alt="`QR Check-in ${booking.code}`"
                        width="180"
                        height="180"
                        class="h-[180px] w-[180px]"
                    />
                </div>
                <div class="space-y-1.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-success">
                        Tiket Siap Digunakan
                    </p>
                    <h2 class="font-display text-2xl font-bold text-ink">
                        Kode Booking: {{ booking.code }}
                    </h2>
                    <p class="text-sm text-ink-muted">
                        Tunjukkan QR Code ini kepada staf venue saat tiba di lokasi. QR hanya berlaku satu kali.
                    </p>
                    <p v-if="booking.qr_valid_from && booking.qr_valid_until" class="text-xs font-medium text-ink">
                        Berlaku: {{ formatTime(booking.qr_valid_from, booking.venue.timezone) }} – {{ formatTime(booking.qr_valid_until, booking.venue.timezone) }}
                    </p>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-[var(--radius-card)] border border-border bg-surface p-6">
                    <h2 class="font-display text-xl font-bold text-ink">
                        Detail Jadwal &amp; Biaya
                    </h2>
                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3 border-b border-border pb-2">
                            <dt class="text-ink-muted">Venue</dt>
                            <dd class="text-right font-semibold text-ink">{{ booking.venue.name }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-border pb-2">
                            <dt class="text-ink-muted">Lapangan</dt>
                            <dd class="text-right font-semibold text-ink">{{ booking.court.name }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-border pb-2">
                            <dt class="text-ink-muted">Tanggal Main</dt>
                            <dd class="text-right font-semibold text-ink">
                                {{ formatDate(booking.starts_at, booking.venue.timezone) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-border pb-2">
                            <dt class="text-ink-muted">Jam</dt>
                            <dd class="text-right font-semibold text-ink">
                                {{ formatTimeRange(booking.starts_at, booking.ends_at, booking.venue.timezone) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-border pb-2">
                            <dt class="text-ink-muted">Pembooking</dt>
                            <dd class="text-right font-semibold text-ink">
                                {{ booking.customer_name }} ({{ formatPhone(booking.customer_phone) }})
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-border pb-2">
                            <dt class="text-ink-muted">Total Tarif</dt>
                            <dd class="text-right font-bold text-ink">{{ formatRupiah(booking.price_total) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">
                                {{ booking.payment_mode === 'full' ? 'Nominal Bayar (Lunas)' : 'Nominal DP Wajib' }}
                            </dt>
                            <dd class="text-right text-base font-bold text-primary">
                                {{ formatRupiah(payableAmount) }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="canCancel" class="mt-6 border-t border-border pt-4">
                        <Button
                            type="button"
                            variant="secondary"
                            class="w-full"
                            @click="showCancelDialog = true"
                        >
                            Batalkan Pesanan
                        </Button>
                        <p
                            v-if="cancelForm.errors.booking"
                            role="alert"
                            class="mt-2 text-xs font-semibold text-danger"
                        >
                            {{ cancelForm.errors.booking }}
                        </p>
                    </div>
                </section>

                <div class="space-y-6">
                    <section
                        v-if="canUploadProof"
                        class="rounded-[var(--radius-card)] border border-border bg-surface p-6"
                    >
                        <h2 class="font-display text-xl font-bold text-ink">
                            Tujuan Transfer &amp; Unggah Bukti
                        </h2>

                        <div v-if="payment_accounts.length > 0" class="mt-4 space-y-3">
                            <div
                                v-for="account in payment_accounts"
                                :key="account.id"
                                class="rounded-[var(--radius-control)] border border-border bg-canvas p-3.5 text-sm"
                            >
                                <template v-if="account.kind === 'bank'">
                                    <p class="font-bold text-ink">{{ account.bank_name }}</p>
                                    <p class="mt-0.5 font-mono text-base font-bold text-primary">
                                        {{ account.account_number }}
                                    </p>
                                    <p class="text-xs text-ink-muted">a.n. {{ account.account_holder }}</p>
                                </template>
                                <template v-else>
                                    <p class="font-bold text-ink">QRIS {{ booking.venue.name }}</p>
                                    <img
                                        v-if="account.qris_image_url"
                                        :src="account.qris_image_url"
                                        :alt="`Kode QRIS ${booking.venue.name}`"
                                        width="220"
                                        height="220"
                                        class="mt-2 rounded-[var(--radius-control)] border border-border bg-surface object-contain"
                                    />
                                </template>
                            </div>
                        </div>

                        <p v-else class="mt-3 text-sm text-ink-muted">
                            Belum ada rekening tujuan aktif yang ditampilkan untuk venue ini.
                        </p>

                        <form class="mt-5 space-y-4 border-t border-border pt-4" @submit.prevent="submitProof">
                            <div>
                                <label for="proof-method" class="block text-xs font-semibold text-ink">
                                    Metode Pembayaran
                                </label>
                                <select
                                    id="proof-method"
                                    v-model="proofForm.method"
                                    class="mt-1 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                >
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="qris">QRIS</option>
                                </select>
                            </div>

                            <div>
                                <label for="proof-file" class="block text-xs font-semibold text-ink">
                                    Bukti Pembayaran (JPG atau PNG, maks 2 MB)
                                </label>
                                <input
                                    id="proof-file"
                                    type="file"
                                    accept="image/jpeg,image/png"
                                    :aria-invalid="Boolean(proofForm.errors.proof || clientFileError)"
                                    :aria-describedby="proofForm.errors.proof || clientFileError ? 'error-proof-file' : undefined"
                                    class="mt-1 block min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink file:mr-3 file:rounded file:border-0 file:bg-canvas file:px-3 file:py-1 file:text-xs file:font-semibold file:text-ink"
                                    @change="onFileChange"
                                />
                                <p
                                    v-if="proofForm.errors.proof || clientFileError"
                                    id="error-proof-file"
                                    class="mt-1 text-xs font-semibold text-danger"
                                >
                                    {{ proofForm.errors.proof || clientFileError }}
                                </p>
                            </div>

                            <div>
                                <label for="proof-note" class="block text-xs font-semibold text-ink">
                                    Catatan (Opsional)
                                </label>
                                <input
                                    id="proof-note"
                                    v-model="proofForm.note"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Nama pengirim rekening"
                                    class="mt-1 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                />
                            </div>

                            <Button type="submit" :loading="proofForm.processing" class="w-full">
                                Unggah Bukti Pembayaran
                            </Button>
                        </form>
                    </section>

                    <section class="rounded-[var(--radius-card)] border border-border bg-surface p-6">
                        <h2 class="font-display text-xl font-bold text-ink">
                            Riwayat Bukti Pembayaran
                        </h2>

                        <div v-if="booking.payments.length === 0" class="mt-3 text-sm text-ink-muted">
                            Belum ada bukti pembayaran yang diunggah.
                        </div>

                        <ul v-else class="mt-4 space-y-3">
                            <li
                                v-for="payment in booking.payments"
                                :key="payment.ulid"
                                class="rounded-[var(--radius-control)] border border-border bg-canvas p-3.5 text-sm"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-ink">
                                        {{ formatRupiah(payment.amount) }} ({{ payment.method.toUpperCase() }})
                                    </span>
                                    <span class="text-xs font-semibold text-ink-muted">
                                        {{ payment.status }}
                                    </span>
                                </div>
                                <p v-if="payment.reject_reason" class="mt-1 text-xs font-semibold text-danger">
                                    Alasan: {{ payment.reject_reason }}
                                </p>
                                <a
                                    v-if="payment.proof_url"
                                    :href="payment.proof_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-2 inline-block text-xs font-semibold text-primary hover:underline"
                                >
                                    Lihat Bukti Terunggah &rarr;
                                </a>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="showCancelDialog"
            title="Batalkan Pesanan?"
            description="Slot yang Anda tahan akan dilepas kembali untuk pengunjung lain. Lanjutkan pembatalan pesanan ini?"
            confirm-label="Ya, Batalkan"
            cancel-label="Kembali"
            :loading="cancelForm.processing"
            @close="showCancelDialog = false"
            @confirm="confirmCancel"
        />
    </PublicLayout>
</template>
