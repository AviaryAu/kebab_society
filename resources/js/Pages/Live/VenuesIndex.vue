<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';

defineProps({
    venues: { type: Array, default: () => [] },
});
</script>

<template>
    <Head>
        <title>Venues</title>
        <meta name="description" content="Discover Sydney venues across music, comedy, nightlife and culture." />
    </Head>

    <SocietyLayout>
        <section class="border-b border-ink">
            <div class="ks-container py-14 lg:py-20">
                <p class="label-caps text-charcoal">Places</p>
                <h1 class="mt-5 text-6xl leading-none lg:text-8xl">Venues</h1>
                <p class="mt-6 max-w-xl text-lg text-charcoal">
                    The rooms, theatres and yards where Sydney actually happens.
                </p>
            </div>
        </section>

        <section>
            <div class="ks-container py-10 lg:py-14">
                <div class="grid gap-x-16 gap-y-14 md:grid-cols-2 lg:grid-cols-3">
                    <article v-for="venue in venues" :key="venue.slug">
                        <Link :href="`/venues/${venue.slug}`" class="ks-anim block">
                            <div class="ks-media aspect-[4/3]">
                                <img :src="venue.image" :alt="venue.name" loading="lazy" />
                            </div>
                            <p class="label-caps mt-5 text-charcoal">{{ venue.suburb }}</p>
                            <h2 class="mt-2.5 text-3xl leading-tight">{{ venue.name }}</h2>
                            <p class="mt-2 text-base text-charcoal">{{ venue.description }}</p>
                        </Link>
                    </article>
                </div>
            </div>
        </section>
    </SocietyLayout>
</template>
