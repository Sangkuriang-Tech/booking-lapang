<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import { formatNumber, formatRupiah } from '@/Utils/format';

const props = defineProps({
    venues: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ sport: '', city: '', q: '' }),
    },
    available_sports: {
        type: Array,
        default: () => [],
    },
    available_cities: {
        type: Array,
        default: () => [],
    },
});

const filterForm = reactive({
    q: props.filters.q || '',
    sport: props.filters.sport || '',
    city: props.filters.city || '',
});

function applyFilters() {
    const query = {};
    if (filterForm.q.trim() !== '') {
        query.q = filterForm.q.trim();
    }
    if (filterForm.sport !== '') {
        query.sport = filterForm.sport;
    }
    if (filterForm.city !== '') {
        query.city = filterForm.city;
    }

    router.get('/venues', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    filterForm.q = '';
    filterForm.sport = '';
    filterForm.city = '';
    applyFilters();
}
</script>

<template>
    <Head>
        <title>Cari &amp; Booking Lapangan Olahraga</title>
        <meta
            name="description"
            content="Cari dan pesan lapangan futsal, mini soccer, badminton, padel, dan basket secara online di Booking Lapang."
        />
    </Head>

    <PublicLayout>
        <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-primary">
                        Direktori Venue
                    </p>
                    <h1 class="mt-1 font-display text-3xl font-bold text-ink sm:text-4xl">
                        Cari Lapangan Olahraga
                    </h1>
                    <p class="mt-1.5 text-sm text-ink-muted">
                        Pilih cabang olahraga, kota, atau nama venue untuk melihat jadwal slot kosong.
                    </p>
                </div>

                <Link
                    href="/"
                    class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink hover:bg-canvas"
                >
                    Kembali ke Beranda
                </Link>
            </div>

            <form
                class="mt-6 grid gap-3 rounded-[var(--radius-card)] border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <div>
                    <label for="filter-q" class="block text-xs font-semibold text-ink">
                        Nama Venue / Alamat
                    </label>
                    <input
                        id="filter-q"
                        v-model="filterForm.q"
                        type="search"
                        placeholder="Contoh: Antic Futsal"
                        class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none"
                    />
                </div>

                <div>
                    <label for="filter-sport" class="block text-xs font-semibold text-ink">
                        Cabang Olahraga
                    </label>
                    <select
                        id="filter-sport"
                        v-model="filterForm.sport"
                        class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                        @change="applyFilters"
                    >
                        <option value="">Semua Olahraga</option>
                        <option
                            v-for="sport in available_sports"
                            :key="sport.slug"
                            :value="sport.slug"
                        >
                            {{ sport.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label for="filter-city" class="block text-xs font-semibold text-ink">
                        Kota
                    </label>
                    <select
                        id="filter-city"
                        v-model="filterForm.city"
                        class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                        @change="applyFilters"
                    >
                        <option value="">Semua Kota</option>
                        <option
                            v-for="city in available_cities"
                            :key="city.slug"
                            :value="city.slug"
                        >
                            {{ city.name }}
                        </option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <Button type="submit" class="flex-1">
                        Cari Venue
                    </Button>
                    <Button
                        v-if="filters.q || filters.sport || filters.city"
                        type="button"
                        variant="secondary"
                        @click="resetFilters"
                    >
                        Reset
                    </Button>
                </div>
            </form>

            <div v-if="venues.length > 0" class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="venue in venues"
                    :key="venue.ulid"
                    class="flex flex-col overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface transition-transform duration-200 hover:-translate-y-0.5"
                >
                    <div class="aspect-[16/10] w-full overflow-hidden bg-canvas">
                        <img
                            v-if="venue.cover_photo_url"
                            :src="venue.cover_photo_url"
                            :alt="venue.cover_photo_alt"
                            :width="venue.cover_photo_width"
                            :height="venue.cover_photo_height"
                            loading="lazy"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center border-b border-border text-xs font-semibold text-ink-muted"
                        >
                            {{ venue.city }}
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col p-5">
                        <div class="flex flex-wrap items-center gap-1.5 text-xs font-semibold">
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
                            <Link
                                :href="`/venue/${venue.slug}`"
                                prefetch
                                class="hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                            >
                                {{ venue.name }}
                            </Link>
                        </h2>
                        <p class="mt-1 text-sm text-ink-muted">
                            {{ venue.address }}
                        </p>

                        <div class="mt-auto flex items-center justify-between border-t border-border pt-4">
                            <div>
                                <p class="text-xs text-ink-muted">
                                    {{ formatNumber(venue.courts_count) }} lapangan aktif
                                </p>
                                <p v-if="venue.min_price_per_hour" class="text-sm font-bold text-primary">
                                    Mulai {{ formatRupiah(venue.min_price_per_hour) }}/jam
                                </p>
                            </div>

                            <Link
                                :href="`/venue/${venue.slug}`"
                                prefetch
                                class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                            >
                                Lihat Jadwal
                            </Link>
                        </div>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="mt-8 rounded-[var(--radius-card)] border border-border bg-surface p-8 text-center"
            >
                <p class="font-display text-2xl font-bold text-ink">
                    Venue Tidak Ditemukan
                </p>
                <p class="mt-2 text-sm text-ink-muted">
                    Tidak ada venue aktif yang cocok dengan filter pencarian Anda. Coba ubah kata kunci atau reset filter.
                </p>
                <div class="mt-4">
                    <Button type="button" variant="secondary" @click="resetFilters">
                        Tampilkan Semua Venue
                    </Button>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
