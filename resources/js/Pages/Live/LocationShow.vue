<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';

defineProps({
    location: { type: Object, required: true },
    events: { type: Array, default: () => [] },
    venues: { type: Array, default: () => [] },
});
</script>

<template>
    <Head>
        <title>{{ location.name }}</title>
        <meta :content="`What's on in ${location.name}, Sydney. Events, venues and tonight picks from Keep Sydney Live.`" name="description" />
    </Head>

    <SocietyLayout>
        <section class="border-b border-ink">
            <div class="ks-container py-14 lg:py-20">
                <p class="label-caps text-charcoal">Around Sydney</p>
                <h1 class="mt-5 text-6xl leading-none lg:text-8xl">{{ location.name }}</h1>
            </div>
        </section>

        <section class="border-b border-ink">
            <div class="ks-container py-14 lg:py-20">
                <h2 class="border-b border-ink pb-5 text-4xl lg:text-5xl">What's on</h2>

                <ul v-if="events.length">
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

                <p v-else class="py-10 text-lg text-charcoal">Nothing listed in {{ location.name }} right now.</p>
            </div>
        </section>

        <section v-if="venues.length">
            <div class="ks-container py-14 lg:py-20">
                <h2 class="border-b border-ink pb-5 text-4xl lg:text-5xl">Venues</h2>

                <ul>
                    <li v-for="venue in venues" :key="venue.slug" class="border-b border-ink/15">
                        <Link :href="`/venues/${venue.slug}`" class="ks-anim flex items-baseline justify-between gap-6 py-6">
                            <span class="font-display text-2xl leading-tight">{{ venue.name }}</span>
                            <span class="label-caps shrink-0 text-charcoal">{{ venue.suburb }}</span>
                        </Link>
                    </li>
                </ul>
            </div>
        </section>
    </SocietyLayout>
</template>
