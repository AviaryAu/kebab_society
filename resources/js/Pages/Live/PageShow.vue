<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';

const props = defineProps({
    page: { type: Object, required: true },
    kicker: { type: String, default: 'Keep Sydney Live' },
    related: { type: Array, default: () => [] },
});

const description = computed(
    () => props.page.meta_description || props.page.excerpt || 'Keep Sydney Live.',
);

const articleSchema = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: props.page.title,
    description: description.value,
    datePublished: props.page.published_at,
    url: `https://kslive.au${props.page.url}`,
}));
</script>

<template>
    <Head>
        <title>{{ page.meta_title || page.title }}</title>
        <meta :content="description" name="description" />
        <component
            :is="'script'"
            head-key="schema-article"
            type="application/ld+json"
            v-text="JSON.stringify(articleSchema)"
        />
    </Head>

    <SocietyLayout>
        <article>
            <section class="border-b border-ink">
                <div class="ks-container py-14 lg:py-20">
                    <p class="label-caps text-charcoal">{{ kicker }}</p>
                    <h1 class="mt-5 max-w-4xl text-5xl leading-none lg:text-7xl">{{ page.title }}</h1>
                    <p v-if="page.excerpt" class="mt-6 max-w-2xl text-xl leading-relaxed text-charcoal">
                        {{ page.excerpt }}
                    </p>
                    <p v-if="page.published_label" class="label-caps mt-8 text-charcoal">
                        {{ page.published_label }}
                    </p>
                </div>
            </section>

            <div v-if="page.image" class="ks-media aspect-[16/9] max-h-[65vh] w-full border-b border-ink">
                <img :src="page.image" :alt="page.title" />
            </div>

            <section>
                <div class="ks-container py-12 lg:py-16">
                    <!-- Server-sanitised editor HTML: tags and attributes are allow-listed on save. -->
                    <div v-if="page.body" class="ks-prose max-w-2xl" v-html="page.body"></div>
                    <p v-else class="max-w-2xl text-lg text-charcoal">This one is still being written.</p>
                </div>
            </section>

            <section v-if="related.length" class="border-t border-ink">
                <div class="ks-container py-12 lg:py-16">
                    <h2 class="text-3xl lg:text-4xl">Keep reading</h2>

                    <div class="mt-8 grid gap-x-16 gap-y-10 md:grid-cols-3">
                        <article v-for="item in related" :key="item.slug" class="border-t border-ink pt-5">
                            <p class="label-caps text-charcoal">Guide</p>
                            <h3 class="mt-3 text-2xl leading-tight">
                                <Link :href="item.url" class="ks-link">{{ item.title }}</Link>
                            </h3>
                            <p v-if="item.excerpt" class="mt-3 text-base text-charcoal">{{ item.excerpt }}</p>
                        </article>
                    </div>
                </div>
            </section>
        </article>
    </SocietyLayout>
</template>
