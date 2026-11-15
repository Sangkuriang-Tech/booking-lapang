<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Toast from '@/Components/UI/Toast.vue';

const props = defineProps({
    toast: {
        type: Object,
        default: () => ({ show: false, type: 'info', message: '' }),
    },
});

const emit = defineEmits(['closeToast']);
const mobileMenuOpen = ref(false);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-canvas text-ink">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-[var(--radius-control)] focus:bg-primary focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white"
        >
            Lewati ke konten utama
        </a>

        <header class="sticky top-0 z-40 border-b border-border bg-surface">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <Link
                    href="/"
                    class="inline-flex min-h-[44px] items-center gap-2.5 font-display text-2xl font-bold tracking-tight text-primary"
                >
                    <span
                        class="inline-flex h-8 w-8 items-center justify-center rounded-[var(--radius-control)] bg-primary text-sm font-bold text-accent"
                        aria-hidden="true"
                    >
                        AK
                    </span>
                    <span>ArenaKita</span>
                </Link>

                <nav aria-label="Navigasi utama" class="hidden items-center gap-2 md:flex">
                    <Link
                        href="/"
                        prefetch
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3.5 py-2 text-sm font-semibold text-ink hover:bg-canvas"
                    >
                        Beranda
                    </Link>
                    <a
                        href="#fitur-fondasi"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3.5 py-2 text-sm font-semibold text-ink-muted hover:bg-canvas hover:text-ink"
                    >
                        Komponen UI
                    </a>
                </nav>

                <button
                    type="button"
                    class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-[var(--radius-control)] border border-border bg-surface text-ink hover:bg-canvas md:hidden"
                    :aria-expanded="mobileMenuOpen"
                    aria-controls="mobile-public-nav"
                    aria-label="Buka menu navigasi"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav
                v-if="mobileMenuOpen"
                id="mobile-public-nav"
                aria-label="Navigasi seluler"
                class="border-t border-border bg-surface px-4 py-3 md:hidden"
            >
                <div class="flex flex-col gap-1">
                    <Link
                        href="/"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3 py-2 text-sm font-semibold text-ink hover:bg-canvas"
                        @click="mobileMenuOpen = false"
                    >
                        Beranda
                    </Link>
                    <a
                        href="#fitur-fondasi"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3 py-2 text-sm font-semibold text-ink-muted hover:bg-canvas hover:text-ink"
                        @click="mobileMenuOpen = false"
                    >
                        Komponen UI
                    </a>
                </div>
            </nav>
        </header>

        <main id="main-content" class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-border bg-surface">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm text-ink-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="font-semibold text-ink">ArenaKita — Platform Booking Lapangan Olahraga</p>
                <p>Fondasi Fase F0 · Aksesibilitas &amp; Performa Terverifikasi</p>
            </div>
        </footer>

        <Toast
            :show="props.toast.show"
            :type="props.toast.type"
            :message="props.toast.message"
            @close="emit('closeToast')"
        />
    </div>
</template>
