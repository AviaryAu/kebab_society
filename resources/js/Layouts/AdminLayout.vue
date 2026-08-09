<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SocietyLogo from '../Components/SocietyLogo.vue';
import ArrowRightIcon from '../Components/Icons/ArrowRightIcon.vue';

defineProps({
    title: { type: String, default: 'The Register' },
});

const page = usePage();
const flash = computed(() => page.props.flash?.message ?? null);
const user = computed(() => page.props.auth?.user ?? null);

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <div class="min-h-dvh bg-cream">
        <header class="border-b-2 border-ink bg-char text-garlic">
            <div class="mx-auto flex max-w-[1600px] items-center gap-4 px-4 py-2.5 sm:px-6">
                <Link href="/admin" class="ks-anim h-9 shrink-0" aria-label="Society admin">
                    <SocietyLogo variant="icon" />
                </Link>

                <div class="min-w-0">
                    <p class="label-caps text-gold">Society administration</p>
                    <h1 class="truncate text-base leading-tight text-garlic">{{ title }}</h1>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <Link
                        href="/"
                        class="ks-anim hidden items-center gap-1.5 text-garlic/70 transition-colors hover:text-garlic sm:inline-flex"
                    >
                        <span class="label-caps">View site</span>
                        <ArrowRightIcon :size="14" :stroke-width="2.4" />
                    </Link>
                    <span v-if="user" class="hidden text-xs text-garlic/50 md:inline">{{ user.email }}</span>
                    <button
                        type="button"
                        class="ks-anim border-2 border-garlic/40 px-3 py-1.5 transition-colors hover:border-garlic hover:bg-garlic hover:text-char"
                        @click="logout"
                    >
                        <span class="label-caps">Sign out</span>
                    </button>
                </div>
            </div>
        </header>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <p v-if="flash" class="border-b-2 border-ink bg-lettuce px-4 py-2.5 text-garlic sm:px-6">
                <span class="mx-auto block max-w-[1600px] text-sm font-semibold">{{ flash }}</span>
            </p>
        </Transition>

        <main class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6">
            <slot />
        </main>
    </div>
</template>
