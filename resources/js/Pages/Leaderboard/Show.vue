<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';
import RestaurantCard from '../../Components/RestaurantCard.vue';
import RestaurantPreviewDialog from '../../Components/RestaurantPreviewDialog.vue';
import SocietyLogo from '../../Components/SocietyLogo.vue';
import TrophyIcon from '../../Components/Icons/TrophyIcon.vue';

defineProps({
    board: { type: Object, required: true },
    boards: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
});

const preview = ref(null);
</script>

<template>
    <Head>
        <title>{{ board.title }}</title>
        <meta name="description" :content="`${board.title} — ${board.tagline} Ranked by the Kebab Society.`" />
    </Head>

    <SocietyLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
            <header class="border-2 border-ink bg-char p-6 text-garlic stamped sm:p-8">
                <div class="flex items-start gap-5">
                    <div class="hidden h-20 shrink-0 sm:block">
                        <SocietyLogo variant="seal" />
                    </div>
                    <div class="min-w-0">
                        <p class="label-caps flex items-center gap-1.5 text-gold">
                            <TrophyIcon :size="14" :stroke-width="2.4" />
                            Official standings
                        </p>
                        <h1 class="mt-2 text-3xl leading-none text-garlic sm:text-5xl">{{ board.title }}</h1>
                        <p class="mt-3 max-w-xl text-sm leading-relaxed text-garlic/70">{{ board.tagline }}</p>
                    </div>
                </div>
            </header>

            <nav class="mt-5 flex flex-wrap gap-2" aria-label="Leaderboards">
                <Link
                    v-for="option in boards"
                    :key="option.key"
                    :href="`/leaderboard/${option.key}`"
                    class="ks-anim border-2 border-ink px-3 py-2 transition-colors"
                    :class="option.key === board.key ? 'bg-ink text-garlic' : 'bg-garlic hover:bg-cream-deep'"
                >
                    <span class="label-caps">{{ option.title }}</span>
                </Link>
            </nav>

            <div v-if="entries.length" class="mt-6 space-y-3">
                <RestaurantCard
                    v-for="entry in entries"
                    :key="entry.restaurant.id"
                    :restaurant="entry.restaurant"
                    :rank="entry.rank"
                    @locate="preview = entry.restaurant"
                />
            </div>

            <div v-else class="mt-6 border-2 border-dashed border-ink/30 p-8 text-center">
                <p class="font-display text-xl font-black">No entries yet.</p>
                <p class="mt-1 text-sm text-ink/60">
                    The Society has not gathered enough evidence to rank this category.
                </p>
                <Link
                    href="/"
                    class="ks-anim mt-4 inline-flex items-center border-2 border-ink bg-garlic px-3 py-2 hover:bg-cream-deep"
                >
                    <span class="label-caps">Back to the map</span>
                </Link>
            </div>

            <p class="mt-6 text-xs leading-relaxed text-ink/45">
                Rankings are produced by the Kebab Society's own scoring service and refreshed as evidence changes.
                Placement cannot be purchased.
            </p>
        </div>

        <RestaurantPreviewDialog :restaurant="preview" @close="preview = null" />
    </SocietyLayout>
</template>
