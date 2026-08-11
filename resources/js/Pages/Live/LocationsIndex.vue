<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';

defineProps({
    locations: { type: Array, default: () => [] },
});
</script>

<template>
    <Head>
        <title>Locations</title>
        <meta name="description" content="Explore Sydney events by neighbourhood with Keep Sydney Live." />
    </Head>

    <SocietyLayout>
        <section class="border-b border-ink">
            <div class="ks-container py-14 lg:py-20">
                <p class="label-caps text-charcoal">Explore</p>
                <h1 class="mt-5 text-6xl leading-none lg:text-8xl">Around Sydney</h1>
            </div>
        </section>

        <section>
            <div class="ks-container py-10 lg:py-14">
                <ul>
                    <li v-for="location in locations" :key="location.slug" class="border-b border-ink/15">
                        <Link
                            :href="`/locations/${location.slug}`"
                            class="ks-anim flex items-baseline justify-between gap-6 py-7"
                        >
                            <span class="font-display text-3xl leading-none lg:text-5xl">{{ location.name }}</span>
                            <span class="label-caps shrink-0 text-charcoal">{{ location.count }} listed</span>
                        </Link>
                    </li>
                </ul>
            </div>
        </section>
    </SocietyLayout>
</template>
