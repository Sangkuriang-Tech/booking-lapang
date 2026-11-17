<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useScrollReveal } from '@/Composables/useScrollReveal';
import { formatNumber, formatRupiah } from '@/Utils/format';

defineProps({
    venues: {
        type: Array,
        default: () => [],
    },
});

const sectionRef = ref(null);
const { isRevealed } = useScrollReveal(sectionRef);

function formatPriceRange(venue) {
    if (venue.min_price_per_hour === null) {
        return 'Hubungi venue untuk harga';
    }

    if (venue.max_price_per_hour === null || venue.min_price_per_hour === venue.max_price_per_hour) {
        return `Mulai ${formatRupiah(venue.min_price_per_hour)}/jam`;
    }

    return `${formatRupiah(venue.min_price_per_hour)} – ${formatRupiah(venue.max_price_per_hour)}/jam`;
}
</script>

<template>
    <section
        id="venue-unggulan"
        ref="sectionRef"
        aria-labelledby="heading-venue-unggulan"
        class="reveal-section mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16"
        :class="isRevealed ? 'reveal-section-visible' : 'reveal-section-hidden'"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-primary">
                    Mitra Terverifikasi
                </p>
                <h2 id="heading-venue-unggulan" class="mt-1 font-display text-3xl font-bold text-ink sm:text-4xl">
                    Venue Unggulan Siap Main
                </h2>
            </div>
            <Link
                href="/venues"
                prefetch
                class="inline-flex min-h-[44px] items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
            >
                <span>Jelajahi direktori venue</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                </svg>
            </Link>
        </div>

        <div
            v-if="venues.length > 0"
            class="mt-6 flex snap-x snap-mandatory gap-5 overflow-x-auto pb-3 md:grid md:grid-cols-2 md:overflow-visible md:pb-0 lg:grid-cols-3"
        >
            <article
                v-for="venue in venues"
                :key="venue.ulid"
                class="group flex w-[85%] shrink-0 snap-start flex-col overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-primary/40 hover:shadow-md md:w-auto"
            >
                <Link :href="`/venue/${encodeURIComponent(venue.slug)}`" prefetch class="relative block aspect-[16/10] w-full overflow-hidden bg-canvas">
                    <img
                        v-if="venue.cover_photo"
                        :src="venue.cover_photo.url"
                        :alt="venue.cover_photo.alt_text"
                        :width="venue.cover_photo.width"
                        :height="venue.cover_photo.height"
                        loading="lazy"
                        decoding="async"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                    />
                    <svg
                        v-else
                        viewBox="0 0 800 500"
                        width="800"
                        height="500"
                        role="img"
                        :aria-label="`Ilustrasi lapangan ${venue.name}`"
                        class="h-full w-full bg-primary transition-transform duration-300 group-hover:scale-[1.03]"
                    >
                        <rect width="800" height="500" fill="#0F3B2E" />
                        <rect x="64" y="56" width="672" height="388" rx="8" fill="none" stroke="#DAD8CE" stroke-width="4" />
                        <line x1="400" y1="56" x2="400" y2="444" stroke="#DAD8CE" stroke-width="4" />
                        <circle cx="400" cy="250" r="72" fill="none" stroke="#D4F34A" stroke-width="4" />
                        <circle cx="400" cy="250" r="8" fill="#D4F34A" />
                        <rect x="64" y="156" width="96" height="188" fill="none" stroke="#DAD8CE" stroke-width="4" />
                        <rect x="640" y="156" width="96" height="188" fill="none" stroke="#DAD8CE" stroke-width="4" />
                    </svg>
                </Link>

                <div class="flex flex-1 flex-col p-5">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                        <span class="rounded-[var(--radius-control)] bg-canvas px-2.5 py-1 text-ink">
                            {{ venue.city }}
                        </span>
                        <span
                            v-for="sportName in venue.sports.slice(0, 2)"
                            :key="sportName"
                            class="rounded-[var(--radius-control)] bg-accent px-2.5 py-1 text-ink"
                        >
                            {{ sportName }}
                        </span>
                    </div>

                    <h3 class="mt-3 font-display text-2xl font-bold text-ink">
                        <Link
                            :href="`/venue/${encodeURIComponent(venue.slug)}`"
                            prefetch
                            class="hover:text-primary hover:underline"
                        >
                            {{ venue.name }}
                        </Link>
                    </h3>

                    <p class="mt-1 line-clamp-2 text-sm text-ink-muted">
                        {{ venue.address }}
                    </p>

                    <div class="mt-auto flex items-center justify-between gap-3 border-t border-border pt-4 text-sm">
                        <div>
                            <p class="font-bold text-ink">
                                {{ formatPriceRange(venue) }}
                            </p>
                            <p class="text-xs text-ink-muted">
                                {{ formatNumber(venue.courts_count) }} lapangan tersedia
                            </p>
                        </div>

                        <Link
                            :href="`/venue/${encodeURIComponent(venue.slug)}`"
                            prefetch
                            class="inline-flex min-h-[44px] shrink-0 items-center gap-1 rounded-[var(--radius-control)] border border-border px-3.5 py-2 text-xs font-bold text-ink transition-colors hover:border-primary hover:bg-primary hover:text-white"
                        >
                            <span>Lihat Jadwal</span>
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </article>
        </div>

        <div
            v-else
            class="mt-6 rounded-[var(--radius-card)] border border-border bg-surface p-6 text-sm text-ink-muted"
        >
            Venue unggulan sedang disiapkan. Silakan gunakan pencarian untuk melihat daftar venue terbaru.
        </div>
    </section>
</template>
