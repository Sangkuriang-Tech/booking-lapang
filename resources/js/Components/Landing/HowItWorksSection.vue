<script setup>
import { ref } from 'vue';
import FuturisticSportBackdrop from '@/Components/UI/FuturisticSportBackdrop.vue';
import { useScrollReveal } from '@/Composables/useScrollReveal';

const sectionRef = ref(null);
const { isRevealed } = useScrollReveal(sectionRef);

const steps = [
    {
        number: '01',
        title: 'Pilih Lapangan & Jam Kosong',
        description:
            'Cari venue berdasarkan cabang olahraga dan kota. Pilih satu atau beberapa slot jam berurutan sesuai ketersediaan real-time.',
    },
    {
        number: '02',
        title: 'Kunci Slot & Unggah Bukti Bayar',
        description:
            'Masuk dengan Google saat konfirmasi slot, pilih pembayaran DP atau lunas, lalu transfer ke rekening bank atau QRIS resmi milik venue.',
    },
    {
        number: '03',
        title: 'Validasi Cepat & Check-In QR',
        description:
            'Setelah staf venue menyetujui bukti bayar, tiket QR unik dikirimkan ke email Anda untuk dipindai saat tiba di lapangan.',
    },
];
</script>

<template>
    <section
        id="cara-kerja"
        ref="sectionRef"
        aria-labelledby="heading-cara-kerja"
        class="reveal-section relative overflow-hidden border-y border-border bg-surface"
        :class="isRevealed ? 'reveal-section-visible' : 'reveal-section-hidden'"
    >
        <FuturisticSportBackdrop variant="section" />
        <div class="relative z-10 mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-wider text-primary">
                    Alur Reservasi
                </p>
                <h2 id="heading-cara-kerja" class="mt-1 font-display text-3xl font-bold text-ink sm:text-4xl">
                    Tiga Langkah Mudah Menuju Lapangan
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-ink-muted sm:text-base">
                    Dirancang agar pemain langsung mengamankan jadwal tanpa bentrok dan tanpa proses berbelit.
                </p>
            </div>

            <ol class="mt-8 grid gap-5 md:grid-cols-3">
                <li
                    v-for="step in steps"
                    :key="step.number"
                    class="flex flex-col rounded-[var(--radius-card)] border border-border bg-canvas p-6"
                >
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius-control)] bg-primary font-display text-lg font-bold text-accent">
                        {{ step.number }}
                    </span>
                    <h3 class="mt-4 font-display text-2xl font-bold text-ink">
                        {{ step.title }}
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                        {{ step.description }}
                    </p>
                </li>
            </ol>
        </div>
    </section>
</template>
