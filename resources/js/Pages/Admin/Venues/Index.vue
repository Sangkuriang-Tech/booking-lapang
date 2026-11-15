<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';

const props = defineProps({
    venues: { type: Array, required: true },
    owners: { type: Array, required: true },
});

const form = useForm({
    owner_ulid: props.owners[0]?.ulid || '',
    name: '',
    city: '',
    address: '',
    contact_phone: '',
    timezone: 'Asia/Jakarta',
    is_published: false,
});

function submit() {
    form.post('/admin/venues', {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'city', 'address', 'contact_phone'),
    });
}
</script>

<template>
    <Head title="Kelola Venue" />

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink">Kelola Venue</h1>
                <p class="mt-1 text-sm text-ink-muted">Buat venue baru dan tautkan ke pemilik (kuota paket dicek otomatis).</p>
            </div>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Buat Venue Baru</h2>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <label for="v-owner" class="mb-1 block text-xs font-semibold text-ink">Pemilik Venue</label>
                        <select id="v-owner" v-model="form.owner_ulid" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option v-for="owner in owners" :key="owner.ulid" :value="owner.ulid">{{ owner.name }} ({{ owner.email }})</option>
                        </select>
                        <p v-if="form.errors.owner_id" class="mt-1 text-xs text-red-600">{{ form.errors.owner_id }}</p>
                    </div>
                    <div>
                        <label for="v-name" class="mb-1 block text-xs font-semibold text-ink">Nama Venue</label>
                        <input id="v-name" v-model="form.name" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="v-city" class="mb-1 block text-xs font-semibold text-ink">Kota</label>
                        <input id="v-city" v-model="form.city" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="v-tz" class="mb-1 block text-xs font-semibold text-ink">Zona Waktu</label>
                        <select id="v-tz" v-model="form.timezone" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option value="Asia/Jakarta">WIB (Asia/Jakarta)</option>
                            <option value="Asia/Makassar">WITA (Asia/Makassar)</option>
                            <option value="Asia/Jayapura">WIT (Asia/Jayapura)</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="v-addr" class="mb-1 block text-xs font-semibold text-ink">Alamat</label>
                        <input id="v-addr" v-model="form.address" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Buat Venue</Button>
                    </div>
                </form>
            </Card>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Daftar Seluruh Venue</h2>
                <div class="space-y-2">
                    <div v-for="venue in venues" :key="venue.ulid" class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-control)] border border-border p-3">
                        <div>
                            <p class="text-sm font-bold text-ink">{{ venue.name }} ({{ venue.city }})</p>
                            <p class="text-xs text-ink-muted">Pemilik: {{ venue.owner_name }} · Langganan: {{ venue.subscription_status }}</p>
                        </div>
                        <span class="rounded-full border border-border px-2.5 py-0.5 text-xs font-semibold text-ink">
                            {{ venue.is_published ? 'Publik' : 'Draft' }}
                        </span>
                    </div>
                </div>
            </Card>
        </div>
    </DashboardLayout>
</template>
