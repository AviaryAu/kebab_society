<script setup>
import { Link } from '@inertiajs/vue3';
import KsLogo from './KsLogo.vue';

const year = new Date().getFullYear();

const columns = [
    {
        heading: 'What\u2019s On',
        links: [
            { label: 'Tonight', href: '/events/tonight' },
            { label: 'This Weekend', href: '/events/this-weekend' },
            { label: 'All Events', href: '/events' },
            { label: 'The Map', href: '/map' },
        ],
    },
    {
        heading: 'Sydney',
        links: [
            { label: 'Venues', href: '/venues' },
            { label: 'Locations', href: '/locations' },
            { label: 'Guides', href: '/guides' },
        ],
    },
    {
        heading: 'Sections',
        links: [
            { label: 'Music', href: '/music' },
            { label: 'Comedy', href: '/comedy' },
            { label: 'Food & Drink', href: '/food' },
            { label: 'Nightlife', href: '/nightlife' },
        ],
    },
];
</script>

<template>
    <footer class="border-t border-ink bg-ink text-warm-white">
        <div class="ks-container py-16 lg:py-24">
            <div class="grid gap-12 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
                <div>
                    <!-- The sign-off mirrors the header lockup, at size. Warm white on ink. -->
                    <KsLogo
                        variant="wordmark"
                        mark
                        class="text-[clamp(1.75rem,4vw,2.5rem)] text-warm-white"
                    />

                    <p class="mt-8 max-w-md text-base leading-relaxed text-warm-white/70">
                        An independent guide to Sydney. What is on, what is worth the trip, and where the city feels
                        most alive.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-8 sm:grid-cols-3">
                    <nav v-for="column in columns" :key="column.heading" :aria-label="column.heading">
                        <p class="label-caps text-warm-white/45">{{ column.heading }}</p>
                        <ul class="mt-5 space-y-3">
                            <li v-for="link in column.links" :key="link.href">
                                <Link :href="link.href" class="ks-link text-sm text-warm-white/85">{{ link.label }}</Link>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <div class="mt-16 flex flex-col gap-3 border-t border-warm-white/15 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-warm-white/50">
                    &copy; {{ year }} Keep Sydney Live &middot; Sydney, Australia
                </p>
                <p class="text-xs text-warm-white/50">
                    kslive.au is an independent Sydney events and culture platform.
                </p>
            </div>
        </div>
    </footer>
</template>
