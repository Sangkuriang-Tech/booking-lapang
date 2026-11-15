<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';

const props = defineProps({
    venue: { type: Object, required: true },
    operatingHours: { type: Array, required: true },
    photos: { type: Array, required: true },
    courts: { type: Array, required: true },
});

const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

const venueForm = useForm({
    name: props.venue.name,
    description: props.venue.description || '',
    city: props.venue.city,
    address: props.venue.address,
    contact_phone: props.venue.contact_phone || '',
    timezone: props.venue.timezone,
    hold_minutes: props.venue.hold_minutes,
    dp_percent: props.venue.dp_percent,
    cancel_min_hours: props.venue.cancel_min_hours,
    reupload_minutes: props.venue.reupload_minutes,
    is_published: Boolean(props.venue.is_published),
});

const hoursForm = useForm({
    hours: props.operatingHours.map((h) => ({ ...h })),
});

const photoForm = useForm({
    photo: null,
    court_id: '',
    alt_text: '',
    is_cover: false,
});

const photoInput = ref(null);

function minutesToTime(min) {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

function submitVenue() {
    venueForm.put('/dashboard/settings/venue', { preserveScroll: true });
}

function submitHours() {
    hoursForm.put('/dashboard/settings/operating-hours', { preserveScroll: true });
}

function handleFileChange(event) {
    const file = event.target.files?.[0] || null;
    photoForm.photo = file;
}

function submitPhoto() {
    photoForm
        .transform((data) => ({
            ...data,
            court_id: data.court_id || null,
        }))
        .post('/dashboard/photos', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                photoForm.reset();
                if (photoInput.value) {
                    photoInput.value.value = '';
                }
            },
        });
}

function setCover(id) {
    router.post(`/dashboard/photos/${id}/cover`, {}, { preserveScroll: true });
}

function deletePhoto(id) {
    router.delete(`/dashboard/photos/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengaturan Venue" />

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink">Pengaturan Venue</h1>
                <p class="mt-1 text-sm text-ink-muted">
                    Kelola profil venue, kebijakan booking, jam operasional, dan galeri foto.
                </p>
            </div>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Profil & Kebijakan Booking</h2>
                <form class="space-y-4" @submit.prevent="submitVenue">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="venue-name" class="mb-1 block text-sm font-semibold text-ink">Nama Venue</label>
                            <input id="venue-name" v-model="venueForm.name" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                            <p v-if="venueForm.errors.name" class="mt-1 text-xs text-red-600">{{ venueForm.errors.name }}</p>
                        </div>
                        <div>
                            <label for="venue-city" class="mb-1 block text-sm font-semibold text-ink">Kota</label>
                            <input id="venue-city" v-model="venueForm.city" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                            <p v-if="venueForm.errors.city" class="mt-1 text-xs text-red-600">{{ venueForm.errors.city }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="venue-address" class="mb-1 block text-sm font-semibold text-ink">Alamat Lengkap</label>
                        <input id="venue-address" v-model="venueForm.address" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        <p v-if="venueForm.errors.address" class="mt-1 text-xs text-red-600">{{ venueForm.errors.address }}</p>
                    </div>

                    <div>
                        <label for="venue-desc" class="mb-1 block text-sm font-semibold text-ink">Deskripsi</label>
                        <textarea id="venue-desc" v-model="venueForm.description" rows="3" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="venue-phone" class="mb-1 block text-sm font-semibold text-ink">Nomor Kontak</label>
                            <input id="venue-phone" v-model="venueForm.contact_phone" type="text" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        </div>
                        <div>
                            <label for="venue-tz" class="mb-1 block text-sm font-semibold text-ink">Zona Waktu</label>
                            <select id="venue-tz" v-model="venueForm.timezone" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                                <option value="Asia/Jakarta">WIB (Asia/Jakarta)</option>
                                <option value="Asia/Makassar">WITA (Asia/Makassar)</option>
                                <option value="Asia/Jayapura">WIT (Asia/Jayapura)</option>
                            </select>
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-ink">
                                <input v-model="venueForm.is_published" type="checkbox" class="h-4 w-4 rounded border-border text-primary" />
                                Publikasikan di Pencarian
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-4">
                        <div>
                            <label for="hold-min" class="mb-1 block text-sm font-semibold text-ink">Hold (menit)</label>
                            <input id="hold-min" v-model.number="venueForm.hold_minutes" type="number" min="5" max="120" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        </div>
                        <div>
                            <label for="dp-pct" class="mb-1 block text-sm font-semibold text-ink">Minimal DP (%)</label>
                            <input id="dp-pct" v-model.number="venueForm.dp_percent" type="number" min="0" max="100" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        </div>
                        <div>
                            <label for="cancel-hr" class="mb-1 block text-sm font-semibold text-ink">Batas Batal (jam)</label>
                            <input id="cancel-hr" v-model.number="venueForm.cancel_min_hours" type="number" min="0" max="168" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        </div>
                        <div>
                            <label for="reup-min" class="mb-1 block text-sm font-semibold text-ink">Unggah Ulang (menit)</label>
                            <input id="reup-min" v-model.number="venueForm.reupload_minutes" type="number" min="5" max="1440" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="venueForm.processing">
                            {{ venueForm.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                        </Button>
                    </div>
                </form>
            </Card>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Jam Operasional (Menit dari 00.00, maks 2880 untuk lewat tengah malam)</h2>
                <form class="space-y-3" @submit.prevent="submitHours">
                    <div
                        v-for="row in hoursForm.hours"
                        :key="row.weekday"
                        class="grid items-center gap-3 rounded-[var(--radius-control)] border border-border p-3 sm:grid-cols-4"
                    >
                        <div class="text-sm font-semibold text-ink">{{ dayNames[row.weekday] }}</div>
                        <label class="inline-flex items-center gap-2 text-sm text-ink">
                            <input v-model="row.is_closed" type="checkbox" class="h-4 w-4 rounded border-border text-primary" />
                            Tutup
                        </label>
                        <div>
                            <label class="mb-1 block text-xs text-ink-muted">Buka ({{ minutesToTime(row.opens_min) }})</label>
                            <input v-model.number="row.opens_min" :disabled="row.is_closed" type="number" min="0" max="1439" step="30" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-2.5 py-1.5 text-sm text-ink disabled:opacity-50" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-ink-muted">Tutup ({{ minutesToTime(row.closes_min) }})</label>
                            <input v-model.number="row.closes_min" :disabled="row.is_closed" type="number" min="1" max="2880" step="30" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-2.5 py-1.5 text-sm text-ink disabled:opacity-50" />
                        </div>
                    </div>
                    <p v-if="hoursForm.errors.hours" class="text-xs text-red-600">{{ hoursForm.errors.hours }}</p>
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="hoursForm.processing">
                            {{ hoursForm.processing ? 'Menyimpan...' : 'Simpan Jam Operasional' }}
                        </Button>
                    </div>
                </form>
            </Card>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Galeri Foto Venue & Lapangan</h2>
                <form class="mb-6 grid gap-4 rounded-[var(--radius-control)] border border-border p-4 sm:grid-cols-2" @submit.prevent="submitPhoto">
                    <div>
                        <label for="photo-file" class="mb-1 block text-sm font-semibold text-ink">Pilih Foto (JPG, PNG, WebP maks 5 MB)</label>
                        <input id="photo-file" ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" required class="w-full text-sm text-ink" @change="handleFileChange" />
                        <p v-if="photoForm.errors.photo" class="mt-1 text-xs text-red-600">{{ photoForm.errors.photo }}</p>
                    </div>
                    <div>
                        <label for="photo-alt" class="mb-1 block text-sm font-semibold text-ink">Teks Alternatif (Alt Text)</label>
                        <input id="photo-alt" v-model="photoForm.alt_text" type="text" required placeholder="Contoh: Lapangan Futsal A Indoor" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="photo-court" class="mb-1 block text-sm font-semibold text-ink">Tautkan ke Lapangan (Opsional)</label>
                        <select id="photo-court" v-model="photoForm.court_id" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option value="">Foto Umum Venue</option>
                            <option v-for="court in courts" :key="court.id" :value="court.id">{{ court.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-end justify-between">
                        <label class="inline-flex items-center gap-2 text-sm text-ink">
                            <input v-model="photoForm.is_cover" :disabled="Boolean(photoForm.court_id)" type="checkbox" class="h-4 w-4 rounded border-border text-primary" />
                            Jadikan Sampul Venue
                        </label>
                        <Button type="submit" :disabled="photoForm.processing">
                            {{ photoForm.processing ? 'Mengunggah...' : 'Unggah Foto' }}
                        </Button>
                    </div>
                </form>

                <div v-if="photos.length === 0" class="text-sm text-ink-muted">Belum ada foto yang diunggah.</div>
                <div v-else class="grid gap-4 sm:grid-cols-3">
                    <div v-for="photo in photos" :key="photo.id" class="overflow-hidden rounded-[var(--radius-control)] border border-border bg-canvas">
                        <img :src="photo.url" :alt="photo.alt_text" :width="photo.width" :height="photo.height" class="h-40 w-full object-cover" />
                        <div class="space-y-2 p-3">
                            <p class="text-xs font-semibold text-ink">{{ photo.alt_text }}</p>
                            <p class="text-xs text-ink-muted">{{ photo.court_name || 'Foto Venue' }} · {{ photo.width }}×{{ photo.height }}px</p>
                            <div class="flex items-center justify-between gap-2 pt-1">
                                <button
                                    v-if="!photo.is_cover && !photo.court_id"
                                    type="button"
                                    class="text-xs font-semibold text-primary hover:underline"
                                    @click="setCover(photo.id)"
                                >
                                    Jadikan Sampul
                                </button>
                                <span v-else-if="photo.is_cover" class="rounded bg-primary px-2 py-0.5 text-xs font-semibold text-white">Sampul</span>
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-red-600 hover:underline"
                                    @click="deletePhoto(photo.id)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </DashboardLayout>
</template>
