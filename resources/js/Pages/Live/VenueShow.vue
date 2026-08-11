<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';

const props = defineProps({
    venue: { type: Object, required: true },
    events: { type: Array, default: () => [] },
    nearby: { type: Array, default: () => [] },
});

const placeSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Place',
    name: props.venue.name,
    address: props.venue.address,
    geo: {
        '@type': 'GeoCoordinates',
        latitude: props.venue.latitude,
        longitude: props.venue.longitude,
    },
    url: `https://kslive.au/venues/${props.venue.slug}`,
}));
</script>

<template>
    <Head>
        <title>{{ venue.name }}</title>
        <meta :content="`${venue.name} in ${venue.suburb}. Upcoming events, transport tips and venue details.`" name="description" />
        <component :is="'script'" head-key="schema-place" type="application/ld+json" v-text="JSON.stringify(placeSchema)" />
    </Head>

    <SocietyLayout>
        <article>
            <section class="border-b border-ink">
                <div v-if="venue.image" class="ks-media aspect-[16/9] max-h-[60vh] w-full border-b border-ink">
                    <img :src="venue.image" :alt="venue.name" />
                </div>

                <div class="ks-container grid gap-10 py-12 lg:grid-cols-[1.25fr_0.75fr] lg:gap-20 lg:py-16">
                    <div>
                        <p class="label-caps text-charcoal">Venue &middot; {{ venue.suburb }}</p>
                        <h1 class="mt-5 text-5xl leading-none lg:text-7xl">{{ venue.name }}</h1>
                        <p class="mt-8 max-w-2xl text-xl leading-relaxed text-charcoal">{{ venue.description }}</p>
                        <!-- Server-sanitised editor HTML: tags and attributes are allow-listed on save. -->
                        <div v-if="venue.body" class="ks-prose mt-8 max-w-2xl" v-html="venue.body"></div>
                    </div>

                    <aside class="lg:pt-2">
                        <dl class="border-t border-ink">
                            <div class="border-b border-ink/15 py-4">
                                <dt class="label-caps text-charcoal">Address</dt>
                                <dd class="mt-2 text-base">{{ venue.address }}</dd>
                            </div>

                            <div class="border-b border-ink/15 py-4">
                                <dt class="label-caps text-charcoal">Getting there</dt>
                                <dd class="mt-2 text-base">{{ venue.transport }}</dd>
                            </div>

                            <div class="border-b border-ink/15 py-4">
                                <dt class="label-caps text-charcoal">Elsewhere</dt>
                                <dd class="mt-2 flex flex-col items-start gap-2">
                                    <a :href="venue.website" target="_blank" rel="noopener noreferrer" class="ks-link text-base">
                                        Website
                                    </a>
                                    <a :href="venue.social_url" target="_blank" rel="noopener noreferrer" class="ks-link text-base">
                                        Social
                                    </a>
                                </dd>
                            </div>
                        </dl>

                        <Link href="/map" class="ks-button ks-button--ghost mt-8">See it on the map</Link>
                    </aside>
                </div>
            </section>

            <section v-if="events.length" class="border-b border-ink">
                <div class="ks-container py-14 lg:py-20">
                    <h2 class="border-b border-ink pb-5 text-4xl lg:text-5xl">What's coming up</h2>

                    <ul>
                        <li v-for="event in events" :key="event.slug" class="border-b border-ink/15">
                            <Link
                                :href="`/events/${event.slug}`"
                                class="ks-anim grid items-baseline gap-2 py-6 sm:grid-cols-[9rem_1fr_auto] sm:gap-8"
                            >
                                <span class="label-time text-charcoal">{{ event.day }} &middot; {{ event.time }}</span>
                                <span class="font-display text-2xl leading-tight">{{ event.title }}</span>
                                <span class="label-caps text-charcoal sm:text-right">{{ event.category }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>

            <section v-if="nearby.length">
                <div class="ks-container py-14 lg:py-20">
                    <h2 class="border-b border-ink pb-5 text-4xl lg:text-5xl">Nearby</h2>

                    <ul>
                        <li v-for="item in nearby" :key="item.slug" class="border-b border-ink/15">
                            <Link
                                :href="`/venues/${item.slug}`"
                                class="ks-anim flex items-baseline justify-between gap-6 py-6"
                            >
                                <span class="font-display text-2xl leading-tight">{{ item.name }}</span>
                                <span class="label-caps shrink-0 text-charcoal">{{ item.suburb }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>
        </article>
    </SocietyLayout>
</template>
