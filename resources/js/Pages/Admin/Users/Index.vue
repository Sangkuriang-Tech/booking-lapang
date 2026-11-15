<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';

defineProps({
    users: { type: Array, required: true },
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    is_active: true,
});

function submit() {
    form.post('/admin/users', {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email', 'phone', 'password'),
    });
}

function toggleStatus(user) {
    router.post(
        `/admin/users/${user.ulid}/status`,
        { is_active: !user.is_active },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Akun Pemilik & Staf" />

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink">Akun Pemilik & Staf</h1>
                <p class="mt-1 text-sm text-ink-muted">Buat dan kelola akun login email/password untuk pemilik dan karyawan venue.</p>
            </div>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Buat Akun Baru</h2>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <label for="u-name" class="mb-1 block text-xs font-semibold text-ink">Nama Lengkap</label>
                        <input id="u-name" v-model="form.name" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="u-email" class="mb-1 block text-xs font-semibold text-ink">Email</label>
                        <input id="u-email" v-model="form.email" type="email" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label for="u-phone" class="mb-1 block text-xs font-semibold text-ink">Nomor Telepon (Opsional)</label>
                        <input id="u-phone" v-model="form.phone" type="text" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="u-pass" class="mb-1 block text-xs font-semibold text-ink">Password (min 12 karakter)</label>
                        <input id="u-pass" v-model="form.password" type="password" required minlength="12" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>
                    <div class="sm:col-span-2 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Buat Akun</Button>
                    </div>
                </form>
            </Card>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Daftar Akun</h2>
                <div class="space-y-2">
                    <div v-for="u in users" :key="u.ulid" class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-control)] border border-border p-3">
                        <div>
                            <p class="text-sm font-bold text-ink">{{ u.name }} ({{ u.email }})</p>
                            <p class="text-xs text-ink-muted">
                                Venue: {{ u.venues.length ? u.venues.map((v) => `${v.name} (${v.role})`).join(', ') : 'Belum ditautkan' }}
                            </p>
                        </div>
                        <button type="button" class="text-xs font-semibold text-primary hover:underline" @click="toggleStatus(u)">
                            {{ u.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </div>
                </div>
            </Card>
        </div>
    </DashboardLayout>
</template>
