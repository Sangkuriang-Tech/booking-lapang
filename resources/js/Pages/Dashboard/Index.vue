<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

defineProps({
    courtsCount: {
        type: Number,
        required: true,
    },
    activeVenue: {
        type: Object,
        default: null,
    },
    availableVenues: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Dashboard Venue">
        <meta name="robots" content="noindex" />
    </Head>

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <h1 class="font-display text-3xl font-bold text-ink">
                Ringkasan Operasional
            </h1>

            <div
                v-if="activeVenue && activeVenue.subscription_status === 'grace_period'"
                role="status"
                class="rounded-[var(--radius-control)] border border-warning bg-surface p-4 text-sm text-warning"
            >
                Langganan venue sedang dalam masa tenggang (H+7). Booking online baru dinonaktifkan; validasi pembayaran, check-in QR, dan laporan tetap dapat digunakan.
            </div>
            <div
                v-else-if="activeVenue && activeVenue.subscription_status === 'inactive'"
                role="alert"
                class="rounded-[var(--radius-control)] border border-danger bg-surface p-4 text-sm text-danger"
            >
                Langganan venue tidak aktif. Pembuatan booking baru dikunci sampai paket diperpanjang.
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-[var(--radius-card)] border border-border bg-surface p-6">
                    <p class="text-sm text-ink-muted">Jumlah Lapangan Aktif</p>
                    <p class="mt-2 font-display text-3xl font-bold text-ink">{{ courtsCount }}</p>
                </div>

                <Link
                    href="/dashboard/validations"
                    class="rounded-[var(--radius-card)] border border-border bg-surface p-6 transition-colors hover:border-primary"
                >
                    <p class="text-sm font-semibold text-primary">Validasi Pembayaran</p>
                    <p class="mt-1 text-xs text-ink-muted">Periksa bukti transfer &amp; konfirmasi booking online.</p>
                </Link>

                <Link
                    href="/dashboard/check-in"
                    class="rounded-[var(--radius-card)] border border-border bg-surface p-6 transition-colors hover:border-primary"
                >
                    <p class="text-sm font-semibold text-primary">Scan QR &amp; Check-in</p>
                    <p class="mt-1 text-xs text-ink-muted">Verifikasi tiket QR, catat pelunasan DP, dan tandai selesai.</p>
                </Link>

                <Link
                    href="/dashboard/ots"
                    class="rounded-[var(--radius-card)] border border-border bg-surface p-6 transition-colors hover:border-primary"
                >
                    <p class="text-sm font-semibold text-primary">Booking OTS</p>
                    <p class="mt-1 text-xs text-ink-muted">Buat booking langsung untuk pelanggan yang datang ke venue.</p>
                </Link>

                <Link
                    href="/dashboard/schedule"
                    class="rounded-[var(--radius-card)] border border-border bg-surface p-6 transition-colors hover:border-primary"
                >
                    <p class="text-sm font-semibold text-primary">Jadwal &amp; Blokir Slot</p>
                    <p class="mt-1 text-xs text-ink-muted">Pantau jadwal harian dan blokir slot untuk perawatan.</p>
                </Link>
            </div>
        </div>
    </DashboardLayout>
</template>
