<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';

defineProps({
    guides: { type: Array, default: () => [] },
});
</script>

<template>
    <Head>
        <title>Guides</title>
        <meta name="description" content="Editorial Sydney event guides from Keep Sydney Live." />
    </Head>

    <SocietyLayout>
        <section class="border-b border-ink">
            <div class="ks-container py-14 lg:py-20">
                <p class="label-caps text-charcoal">Editorial</p>
                <h1 class="mt-5 text-6xl leading-none lg:text-8xl">The Guide</h1>
                <p class="mt-6 max-w-xl text-lg text-charcoal">
                    Recommendations from people who actually go out here.
                </p>
            </div>
        </section>

        <section>
            <div class="ks-container py-10 lg:py-14">
                <div class="grid gap-x-16 gap-y-12 md:grid-cols-2">
                    <article v-for="guide in guides" :key="guide.slug" class="border-t border-ink pt-6">
                        <p class="label-caps text-charcoal">Guide</p>
                        <h2 class="mt-3 text-4xl leading-tight">
                            <Link :href="guide.url" class="ks-link">{{ guide.title }}</Link>
                        </h2>
                        <p class="mt-4 text-lg text-charcoal">{{ guide.excerpt }}</p>
                    </article>

                    <p v-if="! guides.length" class="text-lg text-charcoal">
                        No guides published yet. Check back shortly.
                    </p>
                </div>
            </div>
        </section>
    </SocietyLayout>
</template>
