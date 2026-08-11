<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import KsLogo from '../../Components/KsLogo.vue';
import ArrowRightIcon from '../../Components/Icons/ArrowRightIcon.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head>
        <title>Administration</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="flex min-h-dvh items-center justify-center bg-paper px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex justify-center">
                <KsLogo variant="stacked" class="text-[3.5rem]" />
            </div>

            <form class="border-2 border-ink bg-garlic p-6 stamped" @submit.prevent="submit">
                <h1 class="text-xl leading-tight">Members only</h1>
                <p class="mt-1 text-sm text-ink/60">
                    The register is edited by Society administrators.
                </p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="email" class="label-caps text-ink/55">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            autofocus
                            class="mt-1.5 w-full border-2 border-ink bg-cream px-3 py-2.5 outline-none focus:stamped-sm"
                        />
                    </div>

                    <div>
                        <label for="password" class="label-caps text-ink/55">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="mt-1.5 w-full border-2 border-ink bg-cream px-3 py-2.5 outline-none focus:stamped-sm"
                        />
                    </div>

                    <p v-if="form.errors.email" class="border-2 border-tomato bg-tomato/10 px-3 py-2 text-sm text-tomato-deep">
                        {{ form.errors.email }}
                    </p>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 accent-tomato" />
                        Keep me signed in
                    </label>
                </div>

                <button
                    type="submit"
                    class="ks-anim mt-6 flex w-full items-center justify-center gap-2 border-2 border-ink bg-tomato px-4 py-3 text-garlic transition-colors hover:bg-tomato-deep disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <span class="label-caps">{{ form.processing ? 'Checking…' : 'Sign in' }}</span>
                    <ArrowRightIcon :size="16" :stroke-width="2.5" />
                </button>
            </form>

            <p class="mt-4 text-center text-xs text-ink/45">
                Kebab Society · Sydney, Australia
            </p>
        </div>
    </div>
</template>
