<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Card from '@/Components/UI/Card.vue';
import Skeleton from '@/Components/UI/Skeleton.vue';

const sampleName = ref('');
const sampleError = ref('');
const toastState = ref({
    show: false,
    type: 'success',
    message: '',
});

function triggerToast() {
    toastState.value = {
        show: true,
        type: 'success',
        message: 'Komponen Toast aksesibel berhasil ditampilkan.',
    };
}

function validateSample() {
    sampleError.value = sampleName.value.trim() ? '' : 'Nama venue contoh wajib diisi.';
}
</script>

<template>
    <Head>
        <title>Fondasi Sistem</title>
        <meta
            name="description"
            content="ArenaKita adalah platform SaaS booking lapangan olahraga multi-venue dengan performa tinggi, aksesibilitas WCAG 2.2 AA, dan alur reservasi cepat."
        />
    </Head>

    <PublicLayout
        :toast="toastState"
        @close-toast="toastState.show = false"
    >
        <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
            <div class="max-w-2xl">
                <span class="inline-flex items-center rounded-[var(--radius-control)] bg-accent px-3 py-1 text-xs font-bold uppercase tracking-wider text-ink">
                    Fase F0 · Fondasi
                </span>
                <h1 class="mt-4 font-display text-4xl font-bold tracking-tight text-ink sm:text-5xl">
                    Booking Lapangan Olahraga Cepat, Tegas, dan Tanpa Bentrok Jadwal
                </h1>
                <p class="mt-4 text-base leading-relaxed text-ink-muted">
                    Halaman uji fondasi untuk memverifikasi SSR Inertia Vue 3, Tailwind CSS design tokens,
                    tipografi self-hosted, komponen UI dasar, serta target Lighthouse CI.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <Button variant="primary" size="lg" @click="triggerToast">
                        Uji Notifikasi Toast
                    </Button>
                    <Button variant="outline" size="lg" @click="validateSample">
                        Uji Validasi Input
                    </Button>
                </div>
            </div>
        </section>

        <section id="fitur-fondasi" class="mx-auto max-w-6xl px-4 pb-14 sm:px-6">
            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <template #header>
                        <h2 class="font-display text-2xl font-bold text-ink">
                            Komponen Form &amp; Tombol Dasar
                        </h2>
                    </template>

                    <div class="space-y-4">
                        <Input
                            v-model="sampleName"
                            label="Nama Venue Contoh"
                            placeholder="Contoh: Arena Futsal Nusantara"
                            hint="Masukkan nama venue untuk menguji validasi blur."
                            :error="sampleError"
                            required
                            @blur="validateSample"
                        />

                        <div class="flex flex-wrap gap-2.5">
                            <Button variant="primary">Simpan</Button>
                            <Button variant="secondary">Batal</Button>
                            <Button variant="accent">Promo</Button>
                            <Button variant="danger">Hapus</Button>
                        </div>
                    </div>
                </Card>

                <Card>
                    <template #header>
                        <h2 class="font-display text-2xl font-bold text-ink">
                            Skeleton Pulse Opacity (Nol Gradient)
                        </h2>
                    </template>

                    <div class="space-y-4">
                        <Skeleton variant="text" :lines="2" />
                        <Skeleton variant="slot-grid" :slots-count="4" />
                    </div>
                </Card>
            </div>
        </section>
    </PublicLayout>
</template>
