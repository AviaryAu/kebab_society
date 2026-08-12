<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';

const props = defineProps({
    event: { type: Object, required: true },
    venue: { type: Object, default: null },
    related: { type: Array, default: () => [] },
    nearby: { type: Array, default: () => [] },
});

const eventSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Event',
    name: props.event.title,
    startDate: props.event.start_datetime,
    endDate: props.event.end_datetime,
    eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
    eventStatus: 'https://schema.org/EventScheduled',
    image: [props.event.image],
    location: {
        '@type': 'Place',
        name: props.venue?.name ?? props.event.venue,
        address: props.venue?.address ?? `${props.event.suburb}, Sydney`,
    },
    organizer: {
        '@type': 'Organization',
        name: 'Keep Sydney Live',
        url: 'https://kslive.au',
    },
    offers: {
        '@type': 'Offer',
        url: props.event.ticket_url,
        priceCurrency: 'AUD',
        price: (props.event.price ?? '').replace(/[^\d.]/g, '') || '0',
        availability: 'https://schema.org/InStock',
    },
    description: props.event.description,
}));
</script>

<template>
    <Head>
        <title>{{ event.title }}</title>
        <meta name="description" :content="event.description" />
        <link :href="`https://kslive.au/events/${event.slug}`" rel="canonical" />
        <component :is="'script'" head-key="schema-event" type="application/ld+json" v-text="JSON.stringify(eventSchema)" />
    </Head>

    <SocietyLayout>
        <article>
            <!-- Full-bleed image, then the headline. Magazine order. -->
            <section class="border-b border-ink">
                <div
                    v-if="event.image"
                    class="ks-media relative aspect-[16/9] max-h-[70vh] w-full border-b border-ink"
                >
                    <img :src="event.image" :alt="event.title" />
                    <p v-if="event.image_credit" class="label-caps absolute bottom-0 right-0 bg-ink/70 px-2 py-1 text-garlic">
                        {{ event.image_credit }}
                    </p>
                </div>

                <div class="ks-container grid gap-10 py-12 lg:grid-cols-[1.25fr_0.75fr] lg:gap-20 lg:py-16">
                    <div>
                        <p class="label-caps text-charcoal">{{ event.category }}</p>
                        <h1 class="mt-5 text-5xl leading-none lg:text-7xl">{{ event.title }}</h1>
                        <p class="mt-8 max-w-2xl text-xl leading-relaxed text-charcoal">{{ event.description }}</p>
                        <!-- Server-sanitised editor HTML: tags and attributes are allow-listed on save. -->
                        <div v-if="event.body" class="ks-prose mt-8 max-w-2xl" v-html="event.body"></div>
                    </div>

                    <aside class="lg:pt-2">
                        <dl class="border-t border-ink">
                            <div class="border-b border-ink/15 py-4">
                                <dt class="label-caps text-charcoal">When</dt>
                                <dd class="mt-2">
                                    <p class="text-lg">{{ event.date }}</p>
                                    <p class="label-time mt-1.5 text-charcoal">{{ event.time }} &ndash; {{ event.end_time }}</p>
                                </dd>
                            </div>

                            <div class="border-b border-ink/15 py-4">
                                <dt class="label-caps text-charcoal">Where</dt>
                                <dd class="mt-2">
                                    <p class="text-lg">{{ venue?.name ?? event.venue }}</p>
                                    <p class="mt-1 text-sm text-charcoal">
                                        {{ venue?.address ?? `${event.suburb}, Sydney` }}
                                    </p>
                                </dd>
                            </div>

                            <div class="border-b border-ink/15 py-4">
                                <dt class="label-caps text-charcoal">Tickets</dt>
                                <dd class="mt-2 text-lg">From {{ event.price }}</dd>
                            </div>
                        </dl>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <a :href="event.ticket_url" target="_blank" rel="noopener noreferrer" class="ks-button">
                                Get Tickets
                            </a>
                            <Link v-if="venue" :href="`/venues/${venue.slug}`" class="ks-button ks-button--ghost">
                                The venue
                            </Link>
                        </div>

                        <!--
                            Attribution for events we heard about elsewhere. The
                            facts are theirs; the words above are ours.
                        -->
                        <p v-if="event.attribution" class="mt-8 border-t border-ink/15 pt-4 text-sm text-charcoal">
                            Details via
                            <a
                                :href="event.attribution.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ks-link"
                            >{{ event.attribution.name }}</a>
                        </p>
                    </aside>
                </div>
            </section>

            <section v-if="related.length" class="border-b border-ink">
                <div class="ks-container py-14 lg:py-20">
                    <h2 class="border-b border-ink pb-5 text-4xl lg:text-5xl">More {{ event.category }}</h2>

                    <ul>
                        <li v-for="item in related" :key="item.slug" class="border-b border-ink/15">
                            <Link
                                :href="`/events/${item.slug}`"
                                class="ks-anim grid items-baseline gap-2 py-6 sm:grid-cols-[9rem_1fr_auto] sm:gap-8"
                            >
                                <span class="label-time text-charcoal">{{ item.day }} &middot; {{ item.time }}</span>
                                <span class="font-display text-2xl leading-tight">{{ item.title }}</span>
                                <span class="label-caps text-charcoal sm:text-right">{{ item.suburb }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>

            <section v-if="nearby.length">
                <div class="ks-container py-14 lg:py-20">
                    <h2 class="border-b border-ink pb-5 text-4xl lg:text-5xl">Also in {{ event.suburb }}</h2>

                    <ul>
                        <li v-for="item in nearby" :key="item.slug" class="border-b border-ink/15">
                            <Link
                                :href="`/events/${item.slug}`"
                                class="ks-anim grid items-baseline gap-2 py-6 sm:grid-cols-[9rem_1fr_auto] sm:gap-8"
                            >
                                <span class="label-time text-charcoal">{{ item.day }} &middot; {{ item.time }}</span>
                                <span class="font-display text-2xl leading-tight">{{ item.title }}</span>
                                <span class="label-caps text-charcoal sm:text-right">{{ item.venue }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>
        </article>
    </SocietyLayout>
</template>
