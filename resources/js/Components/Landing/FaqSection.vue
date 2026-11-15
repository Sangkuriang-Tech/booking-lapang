<script setup>
import { ref } from 'vue';
import { useScrollReveal } from '@/Composables/useScrollReveal';

const sectionRef = ref(null);
const { isRevealed } = useScrollReveal(sectionRef);

const faqs = [
    {
        question: 'Apakah dua orang bisa memesan jam dan lapangan yang sama secara bersamaan?',
        answer: 'Tidak. Sistem mengunci jadwal di level database PostgreSQL sehingga ketika satu pembooking menahan slot, permintaan kedua untuk jam yang bersinggungan otomatis ditolak.',
    },
    {
        question: 'Kapan saya perlu login saat memesan lapangan?',
        answer: 'Anda dapat mencari venue, melihat detail lapangan, dan memeriksa jadwal kosong tanpa login. Login Google hanya diminta saat Anda mengunci slot untuk membuat booking.',
    },
    {
        question: 'Bagaimana alur pembayaran DP atau pelunasan?',
        answer: 'Setelah slot ditahan, Anda memilih pembayaran DP atau lunas, lalu transfer langsung ke rekening bank atau QRIS resmi milik venue dan mengunggah bukti transfer. Staf venue akan memvalidasi dan mengirimkan tiket QR.',
    },
    {
        question: 'Bagaimana jika pemilik venue ingin mendaftarkan arena olahraga?',
        answer: 'Hubungi tim ArenaKita melalui tombol Hubungi Tim ArenaKita. Setelah akun pemilik dan paket berlangganan aktif, Anda dapat mengelola profil venue, lapangan, harga, rekening, dan akun karyawan.',
    },
];
</script>

<template>
    <section
        id="faq"
        ref="sectionRef"
        aria-labelledby="heading-faq"
        class="reveal-section mx-auto max-w-4xl px-4 py-12 sm:px-6 sm:py-16"
        :class="isRevealed ? 'reveal-section-visible' : 'reveal-section-hidden'"
    >
        <div class="text-center">
            <p class="text-xs font-bold uppercase tracking-wider text-primary">
                Informasi Tambahan
            </p>
            <h2 id="heading-faq" class="mt-1 font-display text-3xl font-bold text-ink sm:text-4xl">
                Pertanyaan yang Sering Diajukan
            </h2>
        </div>

        <div class="mt-8 space-y-3">
            <details
                v-for="item in faqs"
                :key="item.question"
                class="group rounded-[var(--radius-card)] border border-border bg-surface"
            >
                <summary class="flex min-h-[52px] cursor-pointer list-none items-center justify-between gap-4 px-5 py-3.5 text-left text-sm font-bold text-ink sm:text-base">
                    <span>{{ item.question }}</span>
                    <svg
                        class="h-5 w-5 shrink-0 text-ink-muted transition-transform duration-200 group-open:rotate-180"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </summary>
                <div class="border-t border-border px-5 py-4 text-sm leading-relaxed text-ink-muted">
                    {{ item.answer }}
                </div>
            </details>
        </div>
    </section>
</template>
