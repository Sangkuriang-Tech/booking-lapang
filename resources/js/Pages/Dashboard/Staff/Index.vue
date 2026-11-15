<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';

defineProps({
    members: { type: Array, required: true },
    quota: { type: Object, required: true },
});

const form = useForm({
    email: '',
    role: 'staff',
});

function submit() {
    form.post('/dashboard/staff', {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
}

function removeMember(userUlid) {
    router.delete(`/dashboard/staff/${userUlid}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Kelola Karyawan" />

    <DashboardLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl font-bold text-ink">Karyawan & Akses Venue</h1>
                    <p class="mt-1 text-sm text-ink-muted">
                        Tautkan akun karyawan atau pemilik tambahan ke venue aktif ini.
                    </p>
                </div>
                <div class="rounded-[var(--radius-control)] border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink">
                    Kuota Karyawan Owner: {{ quota.used }} / {{ quota.max === null ? 'Tidak Terbatas' : quota.max }}
                </div>
            </div>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Tambah Anggota Tim</h2>
                <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="submit">
                    <div class="sm:col-span-2">
                        <label for="staff-email" class="mb-1 block text-sm font-semibold text-ink">Email Akun Staf</label>
                        <input id="staff-email" v-model="form.email" type="email" required placeholder="staf@example.com" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        <p v-if="form.errors.email || form.errors.user_id" class="mt-1 text-xs text-red-600">
                            {{ form.errors.email || form.errors.user_id }}
                        </p>
                    </div>
                    <div>
                        <label for="staff-role" class="mb-1 block text-sm font-semibold text-ink">Peran di Venue</label>
                        <select id="staff-role" v-model="form.role" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option value="staff">Karyawan (Staff)</option>
                            <option value="owner">Pemilik (Owner)</option>
                        </select>
                    </div>
                    <div class="sm:col-span-3 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Tambahkan ke Venue</Button>
                    </div>
                </form>
            </Card>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Daftar Anggota Venue</h2>
                <div class="space-y-2">
                    <div v-for="member in members" :key="member.user_ulid" class="flex items-center justify-between rounded-[var(--radius-control)] border border-border p-3">
                        <div>
                            <p class="text-sm font-bold text-ink">{{ member.name }}</p>
                            <p class="text-xs text-ink-muted">{{ member.email }} · {{ member.role === 'owner' ? 'Pemilik' : 'Karyawan' }}</p>
                        </div>
                        <button
                            type="button"
                            class="text-xs font-semibold text-red-600 hover:underline"
                            @click="removeMember(member.user_ulid)"
                        >
                            Hapus dari Venue
                        </button>
                    </div>
                </div>
            </Card>
        </div>
    </DashboardLayout>
</template>
