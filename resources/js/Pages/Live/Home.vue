<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';
import ArrowRightIcon from '../../Components/Icons/ArrowRightIcon.vue';

const props = defineProps({
    brand: { type: Object, required: true },
    headline: { type: String, default: 'A good excuse to leave the house.' },
    categories: { type: Array, default: () => [] },
    tonight: { type: Array, default: () => [] },
    weekend: { type: Array, default: () => [] },
    featured: { type: Array, default: () => [] },
    neighbourhoods: { type: Array, default: () => [] },
    guides: { type: Array, default: () => [] },
    venues: { type: Array, default: () => [] },
    mapCounts: { type: Object, default: () => ({ events: 0, venues: 0 }) },
});

/** The hero carries one lead story above the map tile. */
const featuredTiles = computed(() => props.featured.slice(0, 1));

/** The weekend runs as a lead feature plus supporting stories. */
const weekendLead = computed(() => props.weekend[0] ?? null);
const weekendRest = computed(() => props.weekend.slice(1, 4));

const websiteSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'Keep Sydney Live',
    url: 'https://kslive.au/',
    potentialAction: {
        '@type': 'SearchAction',
        target: 'https://kslive.au/events?search={query}',
        'query-input': 'required name=query',
    },
}));

const orgSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'Keep Sydney Live',
    url: 'https://kslive.au/',
    slogan: 'Keep Sydney Live.',
    description: 'Independent Sydney events and culture platform.',
}));
</script>

<template>
    <Head>
        <title>Sydney Events, What's On & Live Culture</title>
        <meta
            name="description"
            content="Keep Sydney Live is an independent Sydney guide to live music, events, nightlife, food, culture and everything happening across the city."
        />
        <link rel="canonical" href="https://kslive.au/" />
        <component :is="'script'" head-key="schema-website" type="application/ld+json" v-text="JSON.stringify(websiteSchema)" />
        <component :is="'script'" head-key="schema-organization" type="application/ld+json" v-text="JSON.stringify(orgSchema)" />
    </Head>

    <SocietyLayout>
        <!-- HERO: an editorial headline. The brand mark lives in the header. -->
        <section class="border-b border-ink">
            <div class="ks-container grid gap-14 py-14 lg:grid-cols-[1.05fr_0.95fr] lg:gap-20 lg:py-24">
                <div class="flex flex-col justify-between">
                    <div>
                        <p class="label-caps text-charcoal">What's on in Sydney</p>
                        <h1 class="mt-6 text-[clamp(3rem,7.5vw,6.5rem)] leading-[0.92]">
                            {{ headline }}
                        </h1>
                    </div>

                    <div class="mt-12">
                        <p class="max-w-lg text-lg leading-relaxed text-charcoal">
                            An independent guide to Sydney. Live music, comedy, theatre, nightlife, food and everything
                            else worth turning up for.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <Link href="/events" class="ks-button">What's On</Link>
                            <Link href="/events/tonight" class="ks-button ks-button--ghost">Tonight</Link>
                        </div>
                    </div>
                </div>

                <!-- Featured: one lead story, then the map. -->
                <div class="flex flex-col gap-8">
                    <article v-for="event in featuredTiles" :key="event.slug" class="border-t border-ink pt-5">
                        <p class="label-caps text-charcoal">{{ event.day }} &middot; {{ event.suburb }}</p>
                        <h2 class="mt-3 text-3xl lg:text-4xl">
                            <Link :href="`/events/${event.slug}`" class="ks-link">{{ event.title }}</Link>
                        </h2>
                        <p class="label-time mt-3 text-charcoal">{{ event.time }} &middot; {{ event.category }}</p>
                    </article>

                    <!-- The map tile: a door into the full explorer. -->
                    <Link href="/map" class="ks-map-tile ks-anim relative block overflow-hidden border border-ink p-6">
                        <span class="ks-map-tile__wash" aria-hidden="true" />

                        <span class="relative block">
                            <span class="label-caps text-charcoal">The Map</span>
                            <span class="mt-3 block font-display text-3xl leading-none">Sydney, live.</span>
                            <span class="mt-3 block text-sm text-charcoal">
                                {{ mapCounts.events }} events and {{ mapCounts.venues }} venues, plotted.
                            </span>
                            <span class="label-caps mt-6 inline-flex items-center gap-2">
                                Open the map
                                <ArrowRightIcon :size="14" :stroke-width="1.75" />
                            </span>
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- TONIGHT: an editorial list, not a grid of boxes. -->
        <section class="border-b border-ink">
            <div class="ks-container py-14 lg:py-20">
                <div class="flex items-baseline justify-between gap-6 border-b border-ink pb-5">
                    <h2 class="text-4xl lg:text-5xl">Tonight</h2>
                    <Link href="/events/tonight" class="label-caps ks-link shrink-0">All of tonight</Link>
                </div>

                <ul>
                    <li v-for="event in tonight" :key="event.slug" class="border-b border-ink/15">
                        <Link
                            :href="`/events/${event.slug}`"
                            class="ks-anim grid items-center gap-4 py-6 sm:grid-cols-[5.5rem_7rem_1fr_auto] sm:gap-8"
                        >
                            <span class="label-time text-charcoal">{{ event.time }}</span>

                            <!-- Empty alt: the title sits right beside it in the same link. -->
                            <span class="ks-media block aspect-[4/3] w-28 sm:w-full">
                                <img :src="event.image" alt="" loading="lazy" />
                            </span>

                            <span class="block">
                                <span class="block font-display text-2xl leading-tight lg:text-3xl">{{ event.title }}</span>
                                <span class="mt-1.5 block text-sm text-charcoal">{{ event.venue }}</span>
                            </span>

                            <span class="label-caps text-charcoal sm:text-right">
                                {{ event.category }} &middot; {{ event.suburb }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </section>

        <!-- THIS WEEKEND: one lead image, three supporting stories. -->
        <section class="border-b border-ink">
            <div class="ks-container py-14 lg:py-20">
                <div class="flex items-baseline justify-between gap-6 border-b border-ink pb-5">
                    <h2 class="text-4xl lg:text-5xl">This Weekend</h2>
                    <Link href="/events/this-weekend" class="label-caps ks-link shrink-0">The whole weekend</Link>
                </div>

                <div v-if="weekendLead" class="mt-10 grid gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:gap-16">
                    <article>
                        <Link :href="`/events/${weekendLead.slug}`" class="ks-anim block">
                            <div class="ks-media aspect-[4/3]">
                                <img :src="weekendLead.image" :alt="weekendLead.title" loading="lazy" />
                            </div>
                            <p class="label-caps mt-5 text-charcoal">
                                {{ weekendLead.day }} &middot; {{ weekendLead.time }} &middot; {{ weekendLead.category }}
                            </p>
                            <h3 class="mt-3 text-4xl leading-none lg:text-5xl">{{ weekendLead.title }}</h3>
                            <p class="mt-4 max-w-xl text-base text-charcoal">{{ weekendLead.description }}</p>
                        </Link>
                    </article>

                    <div>
                        <article v-for="event in weekendRest" :key="event.slug" class="border-t border-ink/15 first:border-t-0">
                            <Link :href="`/events/${event.slug}`" class="ks-anim block py-6 first:pt-0">
                                <p class="label-caps text-charcoal">{{ event.day }} &middot; {{ event.time }}</p>
                                <h3 class="mt-2.5 text-2xl leading-tight">{{ event.title }}</h3>
                                <p class="mt-2 text-sm text-charcoal">{{ event.venue }} &middot; {{ event.suburb }}</p>
                            </Link>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTIONS: typographic, no boxes. -->
        <section class="border-b border-ink bg-warm-white">
            <div class="ks-container py-14 lg:py-20">
                <h2 class="text-4xl lg:text-5xl">Sections</h2>

                <ul class="mt-8 flex flex-wrap gap-x-10 gap-y-4">
                    <li v-for="category in categories" :key="category.slug">
                        <Link
                            :href="`/${category.slug === 'food-drink' ? 'food' : category.slug}`"
                            class="ks-link font-display text-3xl lg:text-4xl"
                        >
                            {{ category.name }}
                        </Link>
                    </li>
                </ul>
            </div>
        </section>

        <!-- AROUND SYDNEY: the one large pastel moment on the page. -->
        <section class="border-b border-ink bg-powder-blue">
            <div class="ks-container py-14 lg:py-20">
                <div class="flex items-baseline justify-between gap-6">
                    <h2 class="text-4xl lg:text-5xl">Around Sydney</h2>
                    <Link href="/locations" class="label-caps ks-link shrink-0">Every suburb</Link>
                </div>

                <ul class="mt-8 flex flex-wrap gap-x-8 gap-y-3">
                    <li v-for="location in neighbourhoods" :key="location">
                        <Link :href="`/locations/${location}`" class="ks-link text-lg capitalize">
                            {{ location.replaceAll('-', ' ') }}
                        </Link>
                    </li>
                </ul>
            </div>
        </section>

        <section class="border-b border-ink">
            <div class="ks-container py-14 lg:py-20">
                <div class="flex items-baseline justify-between gap-6 border-b border-ink pb-5">
                    <h2 class="text-4xl lg:text-5xl">The Guide</h2>
                    <Link href="/guides" class="label-caps ks-link shrink-0">All guides</Link>
                </div>

                <div class="mt-10 grid gap-x-16 gap-y-10 md:grid-cols-2">
                    <article v-for="guide in guides" :key="guide.slug">
                        <p class="label-caps text-charcoal">Guide</p>
                        <h3 class="mt-3 text-3xl leading-tight">
                            <Link :href="guide.url" class="ks-link">{{ guide.title }}</Link>
                        </h3>
                        <p class="mt-3 text-base text-charcoal">{{ guide.excerpt }}</p>
                    </article>
                </div>
            </div>
        </section>
    </SocietyLayout>
</template>

<style scoped>
/*
 * The map tile wears the city from above, with a paper wash over it so the
 * type stays legible and the block still reads as part of the paper system.
 */
.ks-map-tile {
    background-color: var(--color-warm-white);
    background-image: url('https://images.unsplash.com/photo-1610272396379-6f00d574a41d?auto=format&fit=crop&w=1200&q=80');
    background-size: cover;
    background-position: center;
}

.ks-map-tile__wash {
    position: absolute;
    inset: 0;
    background: linear-gradient(105deg, rgb(252 251 248 / 0.93) 45%, rgb(252 251 248 / 0.6) 100%);
    transition: opacity 400ms ease;
}

.ks-map-tile:hover .ks-map-tile__wash {
    opacity: 0.8;
}
</style>
