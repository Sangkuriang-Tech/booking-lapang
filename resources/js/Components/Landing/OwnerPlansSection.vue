<script setup>
import { ref } from 'vue';
import { useScrollReveal } from '@/Composables/useScrollReveal';
import { formatNumber, formatRupiah } from '@/Utils/format';

defineProps({
    plans: {
        type: Array,
        default: () => [],
    },
});

const sectionRef = ref(null);
const { isRevealed } = useScrollReveal(sectionRef);

function formatQuota(value, suffix) {
    if (value === null || value === undefined) {
        return `Tanpa batas ${suffix}`;
    }

    return `Maks. ${formatNumber(value)} ${suffix}`;
}
</script>

<template>
    <section
        id="pemilik-venue"
        ref="sectionRef"
        aria-labelledby="heading-pemilik-venue"
        class="reveal-section border-y border-border bg-surface"
        :class="isRevealed ? 'reveal-section-visible' : 'reveal-section-hidden'"
    >
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-wider text-primary">
                        Untuk Pemilik Venue
                    </p>
                    <h2 id="heading-pemilik-venue" class="mt-1 font-display text-3xl font-bold text-ink sm:text-4xl">
                        Kelola Venue, Staf, dan Check-In dalam Satu Sistem
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-ink-muted sm:text-base">
                        Pilih paket berlangganan bulanan sesuai skala arena Anda. Isolasi data ketat per venue, validasi transfer, booking OTS, dan laporan terstruktur.
                    </p>
                </div>

                <a
                    href="mailto:halo@arenakita.id?subject=Konsultasi%20Kemitraan%20Venue%20ArenaKita"
                    class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-hover"
                >
                    Hubungi Tim ArenaKita
                </a>
            </div>

            <div class="mt-8 grid gap-5 md:grid-cols-3">
                <article
                    v-for="plan in plans"
                    :key="plan.code"
                    class="flex flex-col rounded-[var(--radius-card)] border p-6"
                    :class="plan.code === 'pro' ? 'border-primary bg-canvas' : 'border-border bg-surface'"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-display text-2xl font-bold text-ink">
                            {{ plan.name }}
                        </h3>
                        <span
                            v-if="plan.code === 'pro'"
                            class="rounded-[var(--radius-control)] bg-accent px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-ink"
                        >
                            Populer
                        </span>
                    </div>

                    <p class="mt-3 font-display text-3xl font-bold text-primary">
                        {{ formatRupiah(plan.price_monthly) }}
                        <span class="font-sans text-sm font-normal text-ink-muted">/bulan</span>
                    </p>

                    <ul class="mt-5 flex-1 space-y-2.5 text-sm text-ink">
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ formatQuota(plan.max_venues, 'venue') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ formatQuota(plan.max_courts_per_venue, 'lapangan aktif/venue') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ formatQuota(plan.max_staff_total, 'akun karyawan') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ formatQuota(plan.max_payment_accounts_per_venue, 'rekening/QRIS aktif') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                            </svg>
                            <span>Retensi bukti bayar {{ formatNumber(plan.proof_retention_days) }} hari</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg
                                class="mt-0.5 h-4 w-4 shrink-0"
                                :class="plan.can_export_reports ? 'text-success' : 'text-ink-muted'"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    v-if="plan.can_export_reports"
                                    fill-rule="evenodd"
                                    d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                    clip-rule="evenodd"
                                />
                                <path
                                    v-else
                                    fill-rule="evenodd"
                                    d="M4 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H4.75A.75.75 0 014 10z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <span :class="plan.can_export_reports ? 'text-ink' : 'text-ink-muted'">
                                {{ plan.can_export_reports ? 'Ekspor laporan CSV & Excel' : 'Laporan harian & bulanan di dashboard' }}
                            </span>
                        </li>
                    </ul>
                </article>
            </div>
        </div>
    </section>
</template>
