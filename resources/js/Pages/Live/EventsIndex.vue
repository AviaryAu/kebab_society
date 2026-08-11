<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    events: { type: Array, default: () => [] },
});
</script>

<template>
    <Head>
        <title>{{ title }} in Sydney</title>
        <meta name="description" :content="description" />
    </Head>

    <SocietyLayout>
        <section class="border-b border-ink">
            <div class="ks-container py-14 lg:py-20">
                <p class="label-caps text-charcoal">Keep Sydney Live</p>
                <h1 class="mt-5 text-6xl leading-none lg:text-8xl">{{ title }}</h1>
                <p class="mt-6 max-w-xl text-lg text-charcoal">{{ description }}</p>
            </div>
        </section>

        <section>
            <div class="ks-container py-10 lg:py-14">
                <ul v-if="events.length">
                    <li v-for="event in events" :key="event.slug" class="border-b border-ink/15">
                        <Link
                            :href="`/events/${event.slug}`"
                            class="ks-anim grid gap-5 py-8 lg:grid-cols-[8rem_1fr_16rem] lg:items-start lg:gap-10"
                        >
                            <div>
                                <p class="label-time text-charcoal">{{ event.day }}</p>
                                <p class="label-time mt-1.5 text-ink">{{ event.time }}</p>
                            </div>

                            <div>
                                <h2 class="text-3xl leading-tight lg:text-4xl">{{ event.title }}</h2>
                                <p class="mt-2 text-base text-charcoal">{{ event.venue }} &middot; {{ event.suburb }}</p>
                                <p class="mt-3 max-w-xl text-base text-charcoal">{{ event.description }}</p>
                                <p class="label-caps mt-4 text-charcoal">
                                    {{ event.category }} &middot; {{ event.price }}
                                </p>
                            </div>

                            <div class="ks-media hidden aspect-[4/3] lg:block">
                                <img :src="event.image" :alt="event.title" loading="lazy" />
                            </div>
                        </Link>
                    </li>
                </ul>

                <div v-else class="border-t border-ink py-20 text-center">
                    <p class="font-display text-3xl">Nothing listed here yet.</p>
                    <p class="mt-3 text-base text-charcoal">Try another section, or see everything that is on.</p>
                    <Link href="/events" class="ks-button mt-8">What's On</Link>
                </div>
            </div>
        </section>
    </SocietyLayout>
</template>
