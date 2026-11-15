<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Toast from '@/Components/UI/Toast.vue';

const props = defineProps({
    activeVenueName: {
        type: String,
        default: '',
    },
    toast: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['closeToast', 'switchVenue']);
const sidebarOpen = ref(false);
const page = usePage();

const resolvedVenueName = computed(() => {
    if (props.activeVenueName) {
        return props.activeVenueName;
    }

    return page.props.activeVenue?.name || 'Mode Superadmin';
});

const canSwitchVenue = computed(() => (page.props.availableVenues?.length || 0) > 1);

const resolvedToast = computed(() => {
    if (props.toast) {
        return props.toast;
    }

    if (page.props.flash?.error) {
        return { show: true, type: 'error', message: page.props.flash.error };
    }

    if (page.props.flash?.success) {
        return { show: true, type: 'success', message: page.props.flash.success };
    }

    return { show: false, type: 'info', message: '' };
});

const currentUrl = computed(() => page.url || '');

const navItems = [
    { href: '/dashboard', label: 'Ringkasan', exact: true },
    { href: '/dashboard/validations', label: 'Validasi Pembayaran', exact: false },
    { href: '/dashboard/check-in', label: 'Scan QR & Check-in', exact: false },
    { href: '/dashboard/ots', label: 'Booking OTS', exact: false },
    { href: '/dashboard/schedule', label: 'Jadwal & Blokir Slot', exact: false },
];

function isActive(item) {
    if (item.exact) {
        return currentUrl.value === item.href;
    }

    return currentUrl.value.startsWith(item.href);
}

const logoutForm = useForm({});
function logout() {
    logoutForm.post('/logout');
}
</script>

<template>
    <div class="flex min-h-screen bg-canvas text-ink">
        <a
            href="#dashboard-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-[var(--radius-control)] focus:bg-primary focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white"
        >
            Lewati ke konten dashboard
        </a>

        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-border bg-surface transition-transform duration-150 md:static"
            aria-label="Sidebar dashboard"
        >
            <div class="flex items-center justify-between border-b border-border px-4 py-3.5">
                <Link
                    href="/dashboard"
                    class="inline-flex min-h-[44px] items-center gap-2 font-display text-xl font-bold text-primary"
                >
                    <span
                        class="inline-flex h-7 w-7 items-center justify-center rounded-[var(--radius-control)] bg-primary text-xs font-bold text-accent"
                        aria-hidden="true"
                    >
                        AK
                    </span>
                    <span>ArenaKita</span>
                </Link>

                <button
                    type="button"
                    aria-label="Tutup sidebar"
                    class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-[var(--radius-control)] text-ink hover:bg-canvas md:hidden"
                    @click="sidebarOpen = false"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                    </svg>
                </button>
            </div>

            <nav aria-label="Navigasi dashboard" class="flex-1 space-y-1 p-3">
                <slot name="sidebar">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        :class="isActive(item)
                            ? 'bg-primary text-white'
                            : 'text-ink hover:bg-canvas'"
                        class="flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3 py-2 text-sm font-semibold transition-colors"
                    >
                        {{ item.label }}
                    </Link>
                </slot>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col">
            <header class="flex items-center justify-between gap-4 border-b border-border bg-surface px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        aria-label="Buka sidebar"
                        class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-[var(--radius-control)] border border-border bg-surface text-ink hover:bg-canvas md:hidden"
                        @click="sidebarOpen = true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div>
                        <p class="text-xs font-medium text-ink-muted">Venue Aktif</p>
                        <p class="text-sm font-bold text-ink">{{ resolvedVenueName }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="canSwitchVenue"
                        href="/dashboard/venues/select"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] border border-border bg-surface px-3.5 py-2 text-sm font-semibold text-primary hover:bg-canvas"
                    >
                        Ganti Venue
                    </Link>

                    <button
                        type="button"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </header>

            <main id="dashboard-content" class="flex-1 p-4 sm:p-6">
                <slot />
            </main>
        </div>

        <Toast
            :show="resolvedToast.show"
            :type="resolvedToast.type"
            :message="resolvedToast.message"
            @close="emit('closeToast')"
        />
    </div>
</template>
