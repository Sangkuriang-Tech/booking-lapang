<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import BookingStatusBadge from '@/Components/Booking/BookingStatusBadge.vue';
import { formatDate, formatRupiah, formatTimeRange } from '@/Utils/format';

defineProps({
    bookings: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head>
        <title>Riwayat Pesanan Saya</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout>
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="font-display text-3xl font-bold text-ink">
                        Riwayat Pesanan Saya
                    </h1>
                    <p class="mt-1 text-sm text-ink-muted">
                        Pantau status pembayaran dan jadwal main Anda di sini.
                    </p>
                </div>

                <Link
                    href="/venues"
                    class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                >
                    Booking Lapangan Baru
                </Link>
            </div>

            <div v-if="bookings.data.length > 0" class="mt-6 space-y-4">
                <article
                    v-for="booking in bookings.data"
                    :key="booking.ulid"
                    class="flex flex-col justify-between gap-4 rounded-[var(--radius-card)] border border-border bg-surface p-5 sm:flex-row sm:items-center"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="font-mono text-xs font-bold text-ink-muted">
                                #{{ booking.code }}
                            </span>
                            <BookingStatusBadge :status="booking.status" />
                        </div>

                        <h2 class="mt-2 font-display text-2xl font-bold text-ink">
                            {{ booking.venue.name }} — {{ booking.court.name }}
                        </h2>
                        <p class="mt-1 text-sm text-ink-muted">
                            {{ formatDate(booking.starts_at, booking.venue.timezone) }} ·
                            {{ formatTimeRange(booking.starts_at, booking.ends_at, booking.venue.timezone) }}
                        </p>
                    </div>

                    <div class="flex flex-col items-start gap-2 sm:items-end">
                        <p class="text-base font-bold text-primary">
                            {{ formatRupiah(booking.price_total) }}
                        </p>
                        <Link
                            :href="`/bookings/${booking.ulid}`"
                            class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] border border-border bg-canvas px-4 py-2 text-sm font-semibold text-ink hover:bg-surface"
                        >
                            Lihat Detail
                        </Link>
                    </div>
                </article>

                <nav
                    v-if="bookings.links && bookings.links.length > 3"
                    aria-label="Navigasi halaman"
                    class="flex flex-wrap justify-center gap-2 pt-4"
                >
                    <template v-for="(link, idx) in bookings.links" :key="idx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-[var(--radius-control)] border px-3 py-2 text-sm font-semibold"
                            :class="link.active ? 'border-primary bg-primary text-white' : 'border-border bg-surface text-ink hover:bg-canvas'"
                        >
                            {{ link.label.replace('&laquo; Previous', 'Sebelumnya').replace('Next &raquo;', 'Berikutnya') }}
                        </Link>
                    </template>
                </nav>
            </div>

            <div
                v-else
                class="mt-8 rounded-[var(--radius-card)] border border-border bg-surface p-8 text-center"
            >
                <p class="font-display text-2xl font-bold text-ink">
                    Belum Ada Pesanan
                </p>
                <p class="mt-2 text-sm text-ink-muted">
                    Anda belum memiliki riwayat booking lapangan. Mulai cari venue favorit Anda sekarang.
                </p>
                <div class="mt-4">
                    <Link
                        href="/venues"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover"
                    >
                        Cari Lapangan
                    </Link>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
