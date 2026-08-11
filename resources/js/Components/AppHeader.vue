<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import KsLogo from './KsLogo.vue';
import ChevronDownIcon from './Icons/ChevronDownIcon.vue';
import SearchIcon from './Icons/SearchIcon.vue';

const page = usePage();
const mobileOpen = ref(false);
const exploreOpen = ref(false);

const primaryLinks = [
    { label: "What's On", href: '/events' },
    { label: 'Map', href: '/map' },
    { label: 'Venues', href: '/venues' },
    { label: 'Guides', href: '/guides' },
];

const exploreLinks = [
    { label: 'Tonight', href: '/events/tonight' },
    { label: 'This Weekend', href: '/events/this-weekend' },
    { label: 'Music', href: '/music' },
    { label: 'Comedy', href: '/comedy' },
    { label: 'Food & Drink', href: '/food' },
    { label: 'Arts', href: '/arts' },
    { label: 'Nightlife', href: '/nightlife' },
    { label: 'Festivals', href: '/festivals' },
    { label: 'Sport', href: '/sport' },
    { label: 'Locations', href: '/locations' },
];

function isActive(href) {
    return href === '/' ? page.url === '/' : page.url.startsWith(href);
}
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-ink bg-paper">
        <div class="ks-container flex items-center gap-8 py-4">
            <Link href="/" class="shrink-0" aria-label="Keep Sydney Live — home">
                <KsLogo variant="wordmark" mark class="text-[26px]" />
            </Link>

            <nav class="ml-auto hidden items-center gap-6 lg:flex" aria-label="Primary">
                <Link
                    v-for="link in primaryLinks"
                    :key="link.href"
                    :href="link.href"
                    class="label-caps transition-opacity hover:opacity-60"
                    :class="isActive(link.href) ? 'text-ink' : 'text-charcoal'"
                >
                    {{ link.label }}
                </Link>

                <div class="relative">
                    <button
                        type="button"
                        class="label-caps inline-flex items-center gap-1.5 text-charcoal transition-opacity hover:opacity-60"
                        :aria-expanded="exploreOpen"
                        aria-controls="ks-explore-nav"
                        @click="exploreOpen = !exploreOpen"
                    >
                        Explore
                        <ChevronDownIcon :size="12" :stroke-width="2" :class="exploreOpen ? 'rotate-180' : ''" />
                    </button>

                    <div
                        v-if="exploreOpen"
                        id="ks-explore-nav"
                        class="absolute right-0 top-full z-40 mt-4 w-52 border border-ink bg-warm-white py-2"
                    >
                        <Link
                            v-for="link in exploreLinks"
                            :key="link.href"
                            :href="link.href"
                            class="block px-4 py-2 text-sm text-charcoal transition-colors hover:bg-paper hover:text-ink"
                            @click="exploreOpen = false"
                        >
                            {{ link.label }}
                        </Link>
                    </div>
                </div>

                <Link href="/events" class="ks-anim text-ink" aria-label="Search events">
                    <SearchIcon :size="17" :stroke-width="1.75" />
                </Link>
            </nav>

            <button
                type="button"
                class="label-caps ml-auto inline-flex items-center gap-2 lg:hidden"
                :aria-expanded="mobileOpen"
                aria-controls="ks-mobile-nav"
                @click="mobileOpen = !mobileOpen"
            >
                Menu
                <ChevronDownIcon :size="12" :stroke-width="2" :class="mobileOpen ? 'rotate-180' : ''" />
            </button>
        </div>

        <nav
            v-if="mobileOpen"
            id="ks-mobile-nav"
            class="border-t border-ink bg-warm-white lg:hidden"
            aria-label="Primary mobile"
        >
            <Link
                v-for="link in [...primaryLinks, ...exploreLinks]"
                :key="link.href"
                :href="link.href"
                class="block border-b border-ink/10 px-5 py-3.5 text-base"
                :class="isActive(link.href) ? 'text-ink' : 'text-charcoal'"
                @click="mobileOpen = false"
            >
                {{ link.label }}
            </Link>
        </nav>
    </header>
</template>
