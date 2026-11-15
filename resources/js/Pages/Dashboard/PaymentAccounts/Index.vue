<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import { formatDate, formatTime } from '@/Utils/format';

const props = defineProps({
    accounts: { type: Array, required: true },
    quota: { type: Object, required: true },
});

const form = useForm({
    kind: 'bank',
    bank_name: '',
    account_number: '',
    account_holder: '',
    qris_image: null,
    current_password: '',
});

const toggleForm = useForm({
    is_active: true,
    current_password: '',
});

function submitCreate() {
    form.post('/dashboard/payment-accounts', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('bank_name', 'account_number', 'account_holder', 'qris_image', 'current_password'),
    });
}

function toggleStatus(account) {
    const password = window.prompt('Masukkan password Anda untuk konfirmasi keamanan perubahan rekening:');
    if (!password) {
        return;
    }

    toggleForm.is_active = !account.is_active;
    toggleForm.current_password = password;
    toggleForm.post(`/dashboard/payment-accounts/${account.id}/toggle`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Rekening & QRIS Tujuan" />

    <DashboardLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl font-bold text-ink">Rekening & QRIS Tujuan</h1>
                    <p class="mt-1 text-sm text-ink-muted">
                        Perubahan rekening mewajibkan konfirmasi password dan jeda visibilitas 24 jam (Threat T10).
                    </p>
                </div>
                <div class="rounded-[var(--radius-control)] border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink">
                    Kuota Aktif: {{ quota.used }} / {{ quota.max === null ? 'Tidak Terbatas' : quota.max }}
                </div>
            </div>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Tambah Rekening Bank atau QRIS</h2>
                <form class="space-y-4" @submit.prevent="submitCreate">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="acc-kind" class="mb-1 block text-sm font-semibold text-ink">Jenis</label>
                            <select id="acc-kind" v-model="form.kind" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                                <option value="bank">Transfer Bank</option>
                                <option value="qris">QRIS</option>
                            </select>
                            <p v-if="form.errors.kind" class="mt-1 text-xs text-red-600">{{ form.errors.kind }}</p>
                        </div>

                        <div v-if="form.kind === 'bank'">
                            <label for="bank-name" class="mb-1 block text-sm font-semibold text-ink">Nama Bank</label>
                            <input id="bank-name" v-model="form.bank_name" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        </div>

                        <div v-if="form.kind === 'bank'">
                            <label for="acc-number" class="mb-1 block text-sm font-semibold text-ink">Nomor Rekening</label>
                            <input id="acc-number" v-model="form.account_number" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        </div>

                        <div v-if="form.kind === 'bank'">
                            <label for="acc-holder" class="mb-1 block text-sm font-semibold text-ink">Atas Nama</label>
                            <input id="acc-holder" v-model="form.account_holder" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        </div>

                        <div v-if="form.kind === 'qris'">
                            <label for="qris-file" class="mb-1 block text-sm font-semibold text-ink">Gambar QRIS (JPG/PNG maks 2 MB)</label>
                            <input id="qris-file" type="file" accept="image/jpeg,image/png" required class="w-full text-sm text-ink" @change="(e) => (form.qris_image = e.target.files?.[0] || null)" />
                            <p v-if="form.errors.qris_image" class="mt-1 text-xs text-red-600">{{ form.errors.qris_image }}</p>
                        </div>
                    </div>

                    <div class="rounded-[var(--radius-control)] border border-amber-300 bg-amber-50 p-3">
                        <label for="reauth-pass" class="mb-1 block text-sm font-semibold text-amber-900">Konfirmasi Password Akun Anda (Wajib)</label>
                        <input id="reauth-pass" v-model="form.current_password" type="password" required autocomplete="current-password" class="w-full rounded-[var(--radius-control)] border border-amber-300 bg-surface px-3 py-2 text-sm text-ink" />
                        <p v-if="form.errors.current_password" class="mt-1 text-xs text-red-600">{{ form.errors.current_password }}</p>
                    </div>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Rekening Tujuan' }}
                        </Button>
                    </div>
                </form>
            </Card>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Daftar Rekening & QRIS</h2>
                <div v-if="accounts.length === 0" class="text-sm text-ink-muted">Belum ada rekening tujuan terdaftar.</div>
                <div v-else class="space-y-3">
                    <div v-for="acc in accounts" :key="acc.id" class="flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-control)] border border-border p-3">
                        <div>
                            <p class="text-sm font-bold text-ink">
                                {{ acc.kind === 'bank' ? `${acc.bank_name} · ${acc.account_number}` : 'QRIS Statis Venue' }}
                            </p>
                            <p v-if="acc.account_holder" class="text-xs text-ink-muted">a.n. {{ acc.account_holder }}</p>
                            <p class="mt-1 text-xs" :class="acc.is_delayed ? 'text-amber-700 font-semibold' : 'text-ink-muted'">
                                {{ acc.is_delayed ? `Menunggu jeda 24 jam · Tampil mulai ${formatDate(acc.visible_from)} ${formatTime(acc.visible_from)}` : 'Aktif tampil ke pembooking' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold" :class="acc.is_active ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-border bg-canvas text-ink-muted'">
                                {{ acc.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <button type="button" class="text-xs font-semibold text-primary hover:underline" @click="toggleStatus(acc)">
                                {{ acc.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </DashboardLayout>
</template>
