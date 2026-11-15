<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatNumber } from '@/Utils/format';

defineProps({
    venues: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ sport: '', city: '' }),
    },
});
</script>

<template>
    <Head>
        <title>Direktori Venue Olahraga</title>
        <meta
            name="description"
            content="Daftar venue lapangan olahraga aktif berdasarkan cabang olahraga dan kota di ArenaKita."
        />
    </Head>

    <PublicLayout>
        <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-primary">
                        Direktori Venue
                    </p>
                    <h1 class="mt-1 font-display text-3xl font-bold text-ink sm:text-4xl">
                        Venue Olahraga Tersedia
                    </h1>
                    <p v-if="filters.sport || filters.city" class="mt-2 text-sm text-ink-muted">
                        Filter aktif:
                        <span v-if="filters.sport" class="font-semibold text-ink">Olahraga "{{ filters.sport }}"</span>
                        <span v-if="filters.sport && filters.city"> · </span>
                        <span v-if="filters.city" class="font-semibold text-ink">Kota "{{ filters.city }}"</span>
                    </p>
                </div>

                <Link
                    href="/"
                    class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink hover:bg-canvas"
                >
                    Kembali ke Beranda
                </Link>
            </div>

            <div v-if="venues.length > 0" class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="venue in venues"
                    :key="venue.ulid"
                    class="flex flex-col rounded-[var(--radius-card)] border border-border bg-surface p-5"
                >
                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                        <span class="rounded-[var(--radius-control)] bg-canvas px-2.5 py-1 text-ink">
                            {{ venue.city }}
                        </span>
                        <span
                            v-for="sportName in venue.sports"
                            :key="sportName"
                            class="rounded-[var(--radius-control)] bg-accent px-2.5 py-1 text-ink"
                        >
                            {{ sportName }}
                        </span>
                    </div>

                    <h2 class="mt-3 font-display text-2xl font-bold text-ink">
                        {{ venue.name }}
                    </h2>
                    <p class="mt-1 text-sm text-ink-muted">
                        {{ venue.address }}
                    </p>
                    <p class="mt-4 border-t border-border pt-3 text-xs font-semibold text-ink-muted">
                        {{ formatNumber(venue.courts_count) }} lapangan aktif · Pilihan slot jadwal hadir di Fase F3.
                    </p>
                </article>
            </div>

            <div
                v-else
                class="mt-8 rounded-[var(--radius-card)] border border-border bg-surface p-6 text-sm text-ink-muted"
            >
                Tidak ditemukan venue aktif untuk filter tersebut.
            </div>
        </section>
    </PublicLayout>
</template>
