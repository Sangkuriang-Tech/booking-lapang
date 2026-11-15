<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
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
const isScrolled = ref(false);

const navItems = [
    { label: 'Kategori', href: '/#kategori' },
    { label: 'Cara Kerja', href: '/#cara-kerja' },
    { label: 'Venue', href: '/#venue-unggulan' },
    { label: 'Untuk Pemilik', href: '/#pemilik-venue' },
    { label: 'FAQ', href: '/#faq' },
];

function handleScroll() {
    isScrolled.value = window.scrollY > 24;
}

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-canvas text-ink">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-[var(--radius-control)] focus:bg-primary focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white"
        >
            Lewati ke konten utama
        </a>

        <header
            class="sticky top-0 z-40 border-b border-border bg-surface transition-[padding] duration-200"
            :class="isScrolled ? 'py-1.5' : 'py-2.5'"
        >
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <Link
                    href="/"
                    prefetch
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

                <nav aria-label="Navigasi utama" class="hidden items-center gap-1 md:flex">
                    <Link
                        href="/"
                        prefetch
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3 py-2 text-sm font-semibold text-ink hover:bg-canvas"
                    >
                        Beranda
                    </Link>
                    <a
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3 py-2 text-sm font-semibold text-ink-muted hover:bg-canvas hover:text-ink"
                    >
                        {{ item.label }}
                    </a>
                    <Link
                        href="/venues"
                        prefetch
                        class="ml-2 inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                    >
                        Cari Lapangan
                    </Link>
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
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3 py-2 text-sm font-semibold text-ink-muted hover:bg-canvas hover:text-ink"
                        @click="mobileMenuOpen = false"
                    >
                        {{ item.label }}
                    </a>
                    <Link
                        href="/venues"
                        class="mt-1 inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-control)] bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                        @click="mobileMenuOpen = false"
                    >
                        Cari Lapangan
                    </Link>
                </div>
            </nav>
        </header>

        <main id="main-content" class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-border bg-surface">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2 lg:col-span-1">
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
                        <p class="mt-2.5 text-sm leading-relaxed text-ink-muted">
                            Platform booking lapangan olahraga multi-venue dengan jadwal real-time, anti double booking, dan verifikasi pembayaran transparan.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-lg font-bold text-ink">Jelajahi</h2>
                        <ul class="mt-3 space-y-1 text-sm">
                            <li>
                                <Link href="/venues" class="inline-flex min-h-[44px] items-center text-ink-muted hover:text-ink">
                                    Cari Lapangan
                                </Link>
                            </li>
                            <li>
                                <a href="/#kategori" class="inline-flex min-h-[44px] items-center text-ink-muted hover:text-ink">
                                    Kategori Olahraga
                                </a>
                            </li>
                            <li>
                                <a href="/#venue-unggulan" class="inline-flex min-h-[44px] items-center text-ink-muted hover:text-ink">
                                    Venue Unggulan
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h2 class="font-display text-lg font-bold text-ink">Mitra Venue</h2>
                        <ul class="mt-3 space-y-1 text-sm">
                            <li>
                                <a href="/#pemilik-venue" class="inline-flex min-h-[44px] items-center text-ink-muted hover:text-ink">
                                    Paket Berlangganan
                                </a>
                            </li>
                            <li>
                                <a href="/#cara-kerja" class="inline-flex min-h-[44px] items-center text-ink-muted hover:text-ink">
                                    Cara Kerja Sistem
                                </a>
                            </li>
                            <li>
                                <a href="/#faq" class="inline-flex min-h-[44px] items-center text-ink-muted hover:text-ink">
                                    Pertanyaan Umum
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h2 class="font-display text-lg font-bold text-ink">Akses &amp; Indeks</h2>
                        <ul class="mt-3 space-y-1 text-sm">
                            <li>
                                <Link href="/staff/login" class="inline-flex min-h-[44px] items-center text-ink-muted hover:text-ink">
                                    Login Staf &amp; Pemilik
                                </Link>
                            </li>
                            <li>
                                <a href="/sitemap.xml" class="inline-flex min-h-[44px] items-center text-ink-muted hover:text-ink">
                                    Sitemap XML
                                </a>
                            </li>
                            <li>
                                <a href="/llms.txt" class="inline-flex min-h-[44px] items-center text-ink-muted hover:text-ink">
                                    Panduan Agentic (llms.txt)
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-8 flex flex-col gap-2 border-t border-border pt-6 text-xs text-ink-muted sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ new Date().getFullYear() }} ArenaKita. Seluruh hak cipta dilindungi.</p>
                    <p>Waktu transaksi mengikuti zona waktu masing-masing venue.</p>
                </div>
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