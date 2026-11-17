<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useScrollReveal } from '@/Composables/useScrollReveal';
import { formatNumber } from '@/Utils/format';

defineProps({
    sports: {
        type: Array,
        default: () => [],
    },
});

const sectionRef = ref(null);
const { isRevealed } = useScrollReveal(sectionRef);

function sportIconKey(slug) {
    const value = String(slug || '').toLowerCase();

    if (value.includes('badminton') || value.includes('bulutangkis')) {
        return 'badminton';
    }
    if (value.includes('basket')) {
        return 'basket';
    }
    if (value.includes('padel') || value.includes('tenis') || value.includes('tennis')) {
        return 'racket';
    }
    if (value.includes('futsal') || value.includes('soccer') || value.includes('sepak')) {
        return 'football';
    }
    if (value.includes('voli') || value.includes('volley')) {
        return 'volleyball';
    }

    return 'default';
}
</script>

<template>
    <section
        id="kategori"
        ref="sectionRef"
        aria-labelledby="heading-kategori"
        class="reveal-section mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16"
        :class="isRevealed ? 'reveal-section-visible' : 'reveal-section-hidden'"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-primary">
                    Cabang Olahraga
                </p>
                <h2 id="heading-kategori" class="mt-1 font-display text-3xl font-bold text-ink sm:text-4xl">
                    Pilih Olahraga Favoritmu
                </h2>
            </div>
            <Link
                href="/venues"
                prefetch
                class="inline-flex min-h-[44px] items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
            >
                <span>Lihat semua venue</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                </svg>
            </Link>
        </div>

        <div
            v-if="sports.length > 0"
            class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <Link
                v-for="sport in sports"
                :key="sport.slug"
                :href="`/venues?sport=${encodeURIComponent(sport.slug)}`"
                prefetch
                class="group flex min-h-[76px] items-center justify-between rounded-[var(--radius-card)] border border-border bg-surface p-5 transition-transform duration-200 hover:-translate-y-0.5 hover:border-primary"
            >
                <div class="flex items-center gap-4">
                    <span
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-canvas text-primary transition-colors group-hover:bg-accent group-hover:text-ink"
                        aria-hidden="true"
                    >
                        <svg v-if="sportIconKey(sport.slug) === 'badminton'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 18l-5-9 3-2 2 4 2-4 3 2-5 9z" />
                            <circle cx="12" cy="19.5" r="2" />
                            <path d="M8.5 7L7 4M12 6.5V3.5M15.5 7L17 4" />
                        </svg>
                        <svg v-else-if="sportIconKey(sport.slug) === 'basket'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 3v18M3 12h18M5.6 5.6c3.2 2.4 3.2 10.4 0 12.8M18.4 5.6c-3.2 2.4-3.2 10.4 0 12.8" />
                        </svg>
                        <svg v-else-if="sportIconKey(sport.slug) === 'racket'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <ellipse cx="14" cy="9" rx="5.5" ry="6.5" transform="rotate(35 14 9)" />
                            <path d="M10 14l-6 6" />
                            <circle cx="6.5" cy="7.5" r="1.5" />
                        </svg>
                        <svg v-else-if="sportIconKey(sport.slug) === 'football'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" />
                            <polygon points="12,7.5 15.8,10.3 14.3,14.8 9.7,14.8 8.2,10.3" />
                            <path d="M12 3v4.5M20.5 9.5l-4.7.8M17.3 19.2l-3-4.4M6.7 19.2l3-4.4M3.5 9.5l4.7.8" />
                        </svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 3a14.5 14.5 0 000 18M3 12h18" />
                        </svg>
                    </span>
                    <div>
                        <h3 class="font-display text-xl font-bold text-ink">
                            {{ sport.name }}
                        </h3>
                        <p class="text-sm text-ink-muted">
                            {{ formatNumber(sport.courts_count) }} lapangan aktif
                        </p>
                    </div>
                </div>
                <svg
                    class="h-5 w-5 shrink-0 text-ink-muted transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-primary"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                </svg>
            </Link>
        </div>

        <p v-else class="mt-6 rounded-[var(--radius-card)] border border-border bg-surface p-6 text-sm text-ink-muted">
            Belum ada kategori olahraga aktif yang tersedia saat ini.
        </p>
    </section>
</template>
