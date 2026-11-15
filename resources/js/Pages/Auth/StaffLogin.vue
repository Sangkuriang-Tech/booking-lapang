<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/staff/login');
}
</script>

<template>
    <Head title="Masuk Staf & Pemilik">
        <meta name="robots" content="noindex" />
    </Head>

    <main class="min-h-screen bg-[#F5F4EF] text-[#0E1B16] flex items-center justify-center px-4 py-12">
        <section class="w-full max-w-md rounded-xl border border-[#DAD8CE] bg-white p-6">
            <h1 class="text-2xl font-bold tracking-tight">Masuk Staf &amp; Pemilik</h1>
            <p class="mt-1 text-sm text-[#53625B]">
                Gunakan akun staf, pemilik venue, atau superadmin.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="block text-sm font-medium">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="mt-1 block w-full rounded-[10px] border border-[#DAD8CE] px-3 py-2.5 text-sm focus:border-[#0F3B2E] focus:outline-none"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-[#B3261E]">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium">Kata Sandi</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-1 block w-full rounded-[10px] border border-[#DAD8CE] px-3 py-2.5 text-sm focus:border-[#0F3B2E] focus:outline-none"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-[#B3261E]">
                        {{ form.errors.password }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-[#53625B]">
                    <input v-model="form.remember" type="checkbox" class="rounded border-[#DAD8CE]" />
                    <span>Ingat saya</span>
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full min-h-[44px] rounded-[10px] bg-[#0F3B2E] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0B2D23] disabled:opacity-60"
                >
                    {{ form.processing ? 'Memproses...' : 'Masuk' }}
                </button>
            </form>
        </section>
    </main>
</template>
