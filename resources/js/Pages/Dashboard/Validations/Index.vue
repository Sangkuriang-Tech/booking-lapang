<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import BookingStatusBadge from '@/Components/Booking/BookingStatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import { formatDate, formatPhone, formatRupiah, formatTime, formatTimeRange } from '@/Utils/format';

defineProps({
    bookings: {
        type: Array,
        default: () => [],
    },
});

const activeModal = ref(null); // { booking, decision: 'reupload' | 'reject' }
const approveForm = useForm({ decision: 'approve', reason: '' });
const decisionForm = useForm({ decision: 'reupload', reason: '' });

function submitApprove(booking) {
    approveForm.decision = 'approve';
    approveForm.reason = '';
    approveForm.post(`/dashboard/validations/${booking.ulid}`, {
        preserveScroll: true,
    });
}

function openDecisionModal(booking, decision) {
    activeModal.value = { booking, decision };
    decisionForm.reset();
    decisionForm.clearErrors();
    decisionForm.decision = decision;
}

function closeModal() {
    activeModal.value = null;
}

function submitDecision() {
    if (!activeModal.value) {
        return;
    }

    decisionForm.post(`/dashboard/validations/${activeModal.value.booking.ulid}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
    });
}
</script>

<template>
    <Head title="Validasi Pembayaran">
        <meta name="robots" content="noindex" />
    </Head>

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-3xl font-bold text-ink">
                    Antrean Validasi Pembayaran
                </h1>
                <p class="mt-1 text-sm text-ink-muted">
                    Cocokkan bukti transfer dengan mutasi rekening venue sebelum menyetujui booking.
                </p>
            </div>

            <div
                v-if="bookings.length === 0"
                class="rounded-[var(--radius-card)] border border-border bg-surface p-8 text-center text-sm text-ink-muted"
            >
                Tidak ada booking yang menunggu validasi pembayaran saat ini.
            </div>

            <div v-else class="space-y-4">
                <article
                    v-for="booking in bookings"
                    :key="booking.ulid"
                    class="rounded-[var(--radius-card)] border border-border bg-surface p-5"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-display text-xl font-bold text-ink">
                                    #{{ booking.code }} — {{ booking.customer_name }}
                                </h2>
                                <BookingStatusBadge :status="booking.status" />
                            </div>
                            <p class="mt-1 text-xs text-ink-muted">
                                {{ booking.court_name }} &middot; {{ formatDate(booking.starts_at, booking.timezone) }}
                                &middot; {{ formatTimeRange(booking.starts_at, booking.ends_at, booking.timezone) }}
                                &middot; {{ formatPhone(booking.customer_phone) }}
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-xs text-ink-muted">
                                {{ booking.payment_mode === 'full' ? 'Nominal Lunas' : 'Nominal DP Wajib' }}
                            </p>
                            <p class="font-display text-xl font-bold text-primary">
                                {{ formatRupiah(booking.latest_payment?.amount ?? booking.dp_required) }}
                            </p>
                            <p class="text-xs text-ink-muted">
                                Total Tarif: {{ formatRupiah(booking.price_total) }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="booking.duplicate_proof_warning"
                        role="alert"
                        class="mt-4 rounded-[var(--radius-control)] border border-danger bg-canvas p-3 text-sm font-semibold text-danger"
                    >
                        Peringatan Keamanan: File bukti transfer ini pernah diunggah pada booking lain (SHA-256 duplikat). Periksa mutasi rekening dengan teliti.
                    </div>

                    <div
                        v-if="booking.status === 'awaiting_reupload' && booking.hold_expires_at"
                        class="mt-4 rounded-[var(--radius-control)] border border-warning bg-canvas p-3 text-xs text-warning"
                    >
                        Menunggu bukti baru dari pembooking hingga {{ formatTime(booking.hold_expires_at, booking.timezone) }}.
                        <span v-if="booking.status_reason">Alasan: {{ booking.status_reason }}</span>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                        <div v-if="booking.latest_payment?.proof_url" class="flex items-center gap-3">
                            <a
                                :href="booking.latest_payment.proof_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] border border-border bg-canvas px-4 py-2 text-sm font-semibold text-primary hover:border-primary"
                            >
                                Lihat Bukti Pembayaran
                            </a>
                            <span class="text-xs text-ink-muted">
                                Metode: {{ booking.latest_payment.method.toUpperCase() }}
                            </span>
                        </div>
                        <p v-else class="text-xs text-ink-muted">
                            Belum ada bukti pembayaran terbaru.
                        </p>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="primary"
                                :disabled="approveForm.processing || booking.status !== 'awaiting_validation'"
                                @click="submitApprove(booking)"
                            >
                                Setujui &amp; Terbitkan QR
                            </Button>

                            <Button
                                type="button"
                                variant="secondary"
                                :disabled="decisionForm.processing"
                                @click="openDecisionModal(booking, 'reupload')"
                            >
                                Minta Bukti Baru
                            </Button>

                            <Button
                                type="button"
                                variant="danger"
                                :disabled="decisionForm.processing"
                                @click="openDecisionModal(booking, 'reject')"
                            >
                                Tolak Langsung
                            </Button>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <div
            v-if="activeModal"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="'modal-title-' + activeModal.booking.ulid"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
            <div class="w-full max-w-md rounded-[var(--radius-card)] border border-border bg-surface p-6">
                <h2 :id="'modal-title-' + activeModal.booking.ulid" class="font-display text-xl font-bold text-ink">
                    {{ activeModal.decision === 'reupload' ? 'Minta Bukti Pembayaran Baru' : 'Tolak Pembayaran Booking' }}
                </h2>
                <p class="mt-1 text-sm text-ink-muted">
                    Booking #{{ activeModal.booking.code }} ({{ activeModal.booking.customer_name }})
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="submitDecision">
                    <div>
                        <label for="validation-reason" class="block text-sm font-semibold text-ink">
                            Alasan (wajib diisi)
                        </label>
                        <textarea
                            id="validation-reason"
                            v-model="decisionForm.reason"
                            rows="3"
                            required
                            :aria-invalid="Boolean(decisionForm.errors.reason)"
                            aria-describedby="validation-reason-error"
                            class="mt-1.5 w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                            placeholder="Contoh: Foto buram, nominal transfer tidak sesuai."
                        />
                        <p v-if="decisionForm.errors.reason" id="validation-reason-error" class="mt-1 text-xs text-danger">
                            {{ decisionForm.errors.reason }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="secondary" @click="closeModal">
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            :variant="activeModal.decision === 'reject' ? 'danger' : 'primary'"
                            :disabled="decisionForm.processing"
                        >
                            {{ activeModal.decision === 'reupload' ? 'Kirim Permintaan' : 'Konfirmasi Tolak' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
