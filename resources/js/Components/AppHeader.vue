<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SocietyLogo from './SocietyLogo.vue';
import MapPinIcon from './Icons/MapPinIcon.vue';
import TrophyIcon from './Icons/TrophyIcon.vue';
import KebabIcon from './Icons/KebabIcon.vue';
import ChevronDownIcon from './Icons/ChevronDownIcon.vue';

const page = usePage();
const mobileOpen = ref(false);

const links = [
    { label: 'The Map', href: '/', icon: MapPinIcon },
    { label: 'Leaderboard', href: '/leaderboard', icon: TrophyIcon },
];

function isActive(href) {
    return href === '/' ? page.url === '/' : page.url.startsWith(href);
}
</script>

<template>
    <header class="sticky top-0 z-40 border-b-2 border-ink bg-cream">
        <div class="mx-auto flex max-w-[1700px] items-center gap-4 px-4 py-2.5 sm:px-6">
            <Link href="/" class="ks-anim shrink-0" aria-label="Kebab Society — home">
                <span class="hidden h-10 sm:block">
                    <SocietyLogo variant="horizontal" />
                </span>
                <span class="block h-10 sm:hidden">
                    <SocietyLogo variant="icon" />
                </span>
            </Link>

            <nav class="ml-auto hidden items-center gap-1 sm:flex" aria-label="Primary">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="ks-anim inline-flex items-center gap-1.5 border-2 px-3 py-2 transition-colors"
                    :class="
                        isActive(link.href)
                            ? 'border-ink bg-ink text-garlic'
                            : 'border-transparent text-ink hover:border-ink hover:bg-garlic'
                    "
                >
                    <component :is="link.icon" :size="15" :stroke-width="2.4" />
                    <span class="label-caps">{{ link.label }}</span>
                </Link>

                <span class="ml-3 hidden items-center gap-1.5 border-2 border-ink bg-char px-3 py-2 text-garlic lg:inline-flex">
                    <KebabIcon :size="15" :stroke-width="2.2" />
                    <span class="label-caps">{{ page.props.society.tagline }}</span>
                </span>
            </nav>

            <button
                type="button"
                class="ks-anim ml-auto inline-flex items-center gap-1.5 border-2 border-ink bg-garlic px-3 py-2 sm:hidden"
                :aria-expanded="mobileOpen"
                aria-controls="ks-mobile-nav"
                @click="mobileOpen = !mobileOpen"
            >
                <span class="label-caps">Menu</span>
                <ChevronDownIcon :size="14" :stroke-width="2.5" :class="mobileOpen ? 'rotate-180' : ''" />
            </button>
        </div>

        <nav
            v-if="mobileOpen"
            id="ks-mobile-nav"
            class="border-t-2 border-ink bg-garlic sm:hidden"
            aria-label="Primary mobile"
        >
            <Link
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="ks-anim flex items-center gap-2 border-b border-ink/10 px-4 py-3"
                :class="isActive(link.href) ? 'bg-ink text-garlic' : ''"
                @click="mobileOpen = false"
            >
                <component :is="link.icon" :size="16" :stroke-width="2.4" />
                <span class="label-caps">{{ link.label }}</span>
            </Link>
        </nav>
    </header>
</template>
