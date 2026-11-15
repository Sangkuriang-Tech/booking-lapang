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
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a14.5 14.5 0 000 18M3 12h18" />
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
