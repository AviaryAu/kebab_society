<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';
import KebabMap from '../../Components/KebabMap.vue';
import OpenStatus from '../../Components/OpenStatus.vue';
import StyleTags from '../../Components/StyleTags.vue';
import SocietyStamp from '../../Components/SocietyStamp.vue';
import PhotoSlideshow from '../../Components/PhotoSlideshow.vue';
import RestaurantCard from '../../Components/RestaurantCard.vue';
import RestaurantPreviewDialog from '../../Components/RestaurantPreviewDialog.vue';
import StarRating from '../../Components/StarRating.vue';
import StarIcon from '../../Components/Icons/StarIcon.vue';
import PhoneIcon from '../../Components/Icons/PhoneIcon.vue';
import GlobeIcon from '../../Components/Icons/GlobeIcon.vue';
import RouteIcon from '../../Components/Icons/RouteIcon.vue';
import MoonIcon from '../../Components/Icons/MoonIcon.vue';
import ArrowRightIcon from '../../Components/Icons/ArrowRightIcon.vue';
import { formatCount, formatPriceLevel } from '../../lib/format';

const props = defineProps({
    restaurant: { type: Object, required: true },
    nearby: { type: Array, default: () => [] },
    map: { type: Object, required: true },
});

const preview = ref(null);

const price = computed(() => formatPriceLevel(props.restaurant.price_level));
const components = computed(() => props.restaurant.rating_breakdown?.components ?? []);
const adjustment = computed(() => props.restaurant.rating_breakdown?.editorial_adjustment ?? 0);
const confidence = computed(() => props.restaurant.rating_breakdown?.confidence_label ?? null);

const metaDescription = computed(() =>
    props.restaurant.is_rated
        ? `${props.restaurant.name} in ${props.restaurant.suburb?.name ?? 'Sydney'} is rated ` +
          `${props.restaurant.kebab_rating} out of 5 by the Kebab Society — ${props.restaurant.tier.label}. ` +
          `${props.restaurant.tier.verdict}`
        : `${props.restaurant.name} in ${props.restaurant.suburb?.name ?? 'Sydney'} is on the Kebab Society ` +
          'register and awaiting a verdict.',
);
</script>

<template>
    <Head>
        <title>{{ restaurant.name }}</title>
        <meta name="description" :content="metaDescription" />
        <meta property="og:title" :content="`${restaurant.name} — Kebab Society`" />
        <meta property="og:description" :content="metaDescription" />
        <meta property="og:image" content="/images/brand/logo-seal.png" />
    </Head>

    <SocietyLayout>
        <article class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
            <nav class="label-caps mb-5 flex items-center gap-2 text-ink/45" aria-label="Breadcrumb">
                <Link href="/" class="ks-link-underline">The Map</Link>
                <span>/</span>
                <span class="text-ink/70">{{ restaurant.suburb?.name }}</span>
            </nav>

            <!-- MASTHEAD -->
            <header class="relative border-2 border-ink bg-garlic stamped">
                <PhotoSlideshow
                    :photos="restaurant.photos ?? []"
                    format="hero"
                    height-class="h-64 sm:h-80"
                    :alt="restaurant.name"
                />

                <div class="p-5 sm:p-7">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                    <img
                        :src="restaurant.marker_icon"
                        :alt="`${restaurant.tier.label} kebab marker`"
                        class="h-28 w-auto shrink-0 sm:h-32"
                        draggable="false"
                    />

                    <div class="min-w-0 flex-1">
                        <p class="label-caps text-tomato">
                            {{ restaurant.suburb?.name }}
                            <span class="text-ink/40">· {{ restaurant.suburb?.region }} · {{ restaurant.postcode }}</span>
                        </p>

                        <h1 class="mt-2 text-3xl leading-none sm:text-5xl">{{ restaurant.name }}</h1>

                        <p class="mt-2 text-sm text-ink/65">
                            {{ restaurant.address_line }}<span v-if="price"> · {{ price }}</span>
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <OpenStatus v-if="restaurant.has_hours" :restaurant="restaurant" />
                            <span
                                v-else
                                class="label-caps border-2 border-dashed border-ink/25 px-2 py-1 text-ink/45"
                            >
                                Trading hours not yet held
                            </span>
                            <span
                                v-if="restaurant.trades_late_night"
                                class="ks-anim inline-flex items-center gap-1.5 border-2 border-ink bg-char px-2 py-1 text-garlic"
                            >
                                <MoonIcon :size="13" :stroke-width="2.4" />
                                <span class="label-caps">Late night</span>
                            </span>
                            <span class="label-caps border-2 border-ink/25 bg-cream px-2 py-1 text-ink/60">
                                {{ restaurant.verification_label }}
                            </span>
                        </div>
                    </div>

                    <SocietyStamp
                        v-if="restaurant.society_approved"
                        :size="120"
                        class="absolute -right-2 -top-6 hidden sm:block"
                    />
                    </div>

                    <p v-if="restaurant.description" class="mt-6 max-w-3xl text-base leading-relaxed text-ink/80">
                        {{ restaurant.description }}
                    </p>
                </div>
            </header>

            <div class="mt-6 grid gap-6 lg:grid-cols-[1.35fr_1fr]">
                <div class="space-y-6">
                    <!-- THE VERDICT -->
                    <section class="border-2 border-ink bg-cream p-5">
                        <h2 class="label-caps text-ink/45">Kebab Society Rating</h2>
                        <div class="mt-3 flex items-end gap-3">
                            <p
                                class="font-display text-7xl font-black leading-none tabular-nums sm:text-8xl"
                                :style="{ color: restaurant.tier.colour }"
                            >
                                {{ restaurant.is_rated ? restaurant.kebab_rating.toFixed(1) : '—' }}
                            </p>
                            <div class="pb-1">
                                <StarRating :rating="restaurant.kebab_rating" :size="26" :colour="restaurant.tier.colour" />
                                <p class="label-caps mt-1.5" :style="{ color: restaurant.tier.colour }">
                                    {{ restaurant.tier.label }}
                                </p>
                                <p class="mt-1 max-w-xs text-sm leading-snug text-ink/70">{{ restaurant.tier.verdict }}</p>
                            </div>
                        </div>

                        <div
                            v-if="restaurant.editorial_note"
                            class="mt-5 border-l-4 border-tomato bg-garlic p-3.5 text-sm italic leading-relaxed"
                        >
                            “{{ restaurant.editorial_note }}”
                            <span class="mt-1 block not-italic label-caps text-ink/40">The Society</span>
                        </div>

                        <!-- Every rating must be explainable. -->
                        <div v-if="components.length" class="mt-6">
                            <h3 class="label-caps text-ink/45">How this rating was reached</h3>
                            <ul class="mt-3 space-y-2.5">
                                <li v-for="component in components" :key="component.key">
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="font-semibold">{{ component.label }}</span>
                                        <span class="tabular-nums text-ink/55">
                                            {{ Math.round(component.weight * 100) }}% weight ·
                                            <span class="font-semibold text-ink">
                                                {{ component.rating.toFixed(2) }}★
                                            </span>
                                        </span>
                                    </div>
                                    <div class="mt-1 h-1.5 w-full bg-cream-deep">
                                        <div
                                            class="h-full bg-char"
                                            :style="{ width: `${Math.max(2, (component.rating / 5) * 100)}%` }"
                                        />
                                    </div>
                                    <p class="mt-1 text-xs text-ink/50">{{ component.detail }}</p>
                                </li>
                            </ul>

                            <p v-if="adjustment !== 0" class="mt-3 border-t-2 border-ink/10 pt-3 text-sm">
                                <span class="font-semibold">Editorial adjustment</span>
                                <span class="ml-1 tabular-nums" :class="adjustment > 0 ? 'text-lettuce' : 'text-tomato'">
                                    {{ adjustment > 0 ? `+${adjustment}` : adjustment }}★
                                </span>
                                <span class="text-ink/50"> — applied by hand, and disclosed.</span>
                            </p>

                            <p v-if="confidence" class="mt-2 text-xs text-ink/45">{{ confidence }}.</p>
                        </div>

                        <p v-else class="mt-5 border-t-2 border-ink/10 pt-4 text-sm leading-relaxed text-ink/60">
                            The Society holds no ratings for this shop yet, so it publishes none. It is on the
                            register and awaiting a visit.
                        </p>
                    </section>

                    <!-- EXTERNAL SIGNAL -->
                    <section class="grid gap-4 sm:grid-cols-2">
                        <div class="border-2 border-ink bg-garlic p-4">
                            <h2 class="label-caps text-ink/45">Google rating</h2>
                            <p class="mt-2 flex items-center gap-2 font-display text-3xl font-black">
                                <StarIcon :size="22" filled :stroke-width="1.5" class="text-gold" />
                                {{ restaurant.google_rating ?? '—' }}
                            </p>
                            <p class="mt-1 text-xs text-ink/55">
                                {{ formatCount(restaurant.google_review_count) }} reviews
                                <span v-if="restaurant.google_data_updated_at">
                                    · updated {{ restaurant.google_data_updated_at }}
                                </span>
                            </p>
                            <p class="mt-2 text-[11px] leading-snug text-ink/40">
                                A separate, external signal. Not the Kebab Society Score.
                            </p>
                        </div>

                        <div class="border-2 border-ink bg-garlic p-4">
                            <h2 class="label-caps text-ink/45">Society activity</h2>
                            <p class="mt-2 font-display text-3xl font-black tabular-nums">
                                {{ formatCount(restaurant.society_review_count) }}
                            </p>
                            <p class="mt-1 text-xs text-ink/55">
                                member reviews · {{ formatCount(restaurant.check_in_count) }} check-ins
                            </p>
                            <p class="mt-2 text-[11px] leading-snug text-ink/40">
                                Source: {{ restaurant.data_source_label }}
                            </p>
                        </div>
                    </section>

                    <section class="border-2 border-ink bg-garlic p-5">
                        <h2 class="label-caps text-ink/45">On the menu</h2>
                        <StyleTags :styles="restaurant.styles" :limit="20" class="mt-3" />
                    </section>
                </div>

                <div class="space-y-6">
                    <!-- LOCATION -->
                    <section class="border-2 border-ink bg-garlic">
                        <div class="h-56">
                            <KebabMap
                                :restaurants="[restaurant]"
                                :config="{ ...map, centre: { lat: restaurant.latitude, lng: restaurant.longitude }, zoom: 15 }"
                                @select="preview = restaurant"
                            />
                        </div>
                        <div class="grid grid-cols-3 border-t-2 border-ink">
                            <a
                                :href="restaurant.directions_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ks-anim flex flex-col items-center gap-1 border-r-2 border-ink p-3 transition-colors hover:bg-cream-deep"
                            >
                                <RouteIcon :size="18" />
                                <span class="label-caps text-[10px]">Directions</span>
                            </a>
                            <a
                                :href="restaurant.phone ? `tel:${restaurant.phone.replace(/\s/g, '')}` : null"
                                class="ks-anim flex flex-col items-center gap-1 border-r-2 border-ink p-3 transition-colors hover:bg-cream-deep"
                                :class="{ 'pointer-events-none opacity-35': !restaurant.phone }"
                            >
                                <PhoneIcon :size="18" />
                                <span class="label-caps text-[10px]">Call</span>
                            </a>
                            <a
                                :href="restaurant.website ?? null"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ks-anim flex flex-col items-center gap-1 p-3 transition-colors hover:bg-cream-deep"
                                :class="{ 'pointer-events-none opacity-35': !restaurant.website }"
                            >
                                <GlobeIcon :size="18" />
                                <span class="label-caps text-[10px]">Website</span>
                            </a>
                        </div>
                    </section>

                    <!-- HOURS -->
                    <section class="border-2 border-ink bg-cream p-5">
                        <h2 class="label-caps text-ink/45">Trading hours</h2>
                        <ul class="mt-3 divide-y divide-ink/10">
                            <li
                                v-for="day in restaurant.weekly_hours"
                                :key="day.day"
                                class="flex items-baseline justify-between gap-3 py-1.5 text-sm"
                                :class="day.is_today ? 'font-bold' : 'text-ink/70'"
                            >
                                <span>{{ day.label }}<span v-if="day.is_today" class="text-tomato"> · today</span></span>
                                <span class="text-right tabular-nums">
                                    <span v-for="session in day.sessions" :key="session" class="block">
                                        {{ session }}
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>

            <!-- NEARBY -->
            <section v-if="nearby.length" class="mt-10">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-2xl">If there is a queue</h2>
                    <Link href="/" class="ks-anim label-caps inline-flex items-center gap-1 text-tomato">
                        All kebabs
                        <ArrowRightIcon :size="14" :stroke-width="2.4" />
                    </Link>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <RestaurantCard
                        v-for="option in nearby"
                        :key="option.id"
                        :restaurant="option"
                        compact
                        @locate="preview = option"
                    />
                </div>
            </section>
        </article>

        <RestaurantPreviewDialog :restaurant="preview" @close="preview = null" />
    </SocietyLayout>
</template>
