<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import StarRating from '../../../Components/StarRating.vue';
import SearchIcon from '../../../Components/Icons/SearchIcon.vue';
import ChevronDownIcon from '../../../Components/Icons/ChevronDownIcon.vue';
import CheckIcon from '../../../Components/Icons/CheckIcon.vue';
import { formatCount } from '../../../lib/format';

const props = defineProps({
    restaurants: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const SEARCH_DEBOUNCE_MS = 300;

const search = ref(props.filters.search ?? '');
let timer = null;

const columns = [
    { key: 'name', label: 'Restaurant', sortable: true },
    { key: 'kebab_rating', label: 'Society', sortable: true },
    { key: 'google_rating', label: 'Google', sortable: true },
    { key: 'google_review_count', label: 'Reviews', sortable: true },
    { key: 'status', label: 'Status', sortable: false },
    { key: 'photos', label: 'Photos', sortable: false },
    { key: 'updated_at', label: 'Updated', sortable: true },
];

function query(changes = {}) {
    const next = {
        search: search.value || undefined,
        status: props.filters.status || undefined,
        rated: props.filters.rated || undefined,
        certified: props.filters.certified || undefined,
        sort: props.filters.sort || undefined,
        direction: props.filters.direction || undefined,
        ...changes,
    };

    router.get('/admin', Object.fromEntries(Object.entries(next).filter(([, v]) => v !== undefined && v !== null)), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function sortBy(column) {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc';
    query({ sort: column, direction, page: undefined });
}

function setFilter(key, value) {
    query({ [key]: props.filters[key] === value ? undefined : value, page: undefined });
}

/** Laravel's pagination labels arrive HTML-escaped; render them as text. */
function pageLabel(label) {
    return label.replace('&laquo;', '‹').replace('&raquo;', '›').replace(/<[^>]*>/g, '').trim();
}

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => query({ page: undefined }), SEARCH_DEBOUNCE_MS);
});
</script>

<template>
    <Head>
        <title>The Register</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout title="The Register">
        <!-- SUMMARY -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div v-for="(value, key) in summary" :key="key" class="border-2 border-ink bg-garlic p-3">
                <p class="label-caps text-ink/45">{{ key }}</p>
                <p class="mt-1 font-display text-2xl font-black tabular-nums">{{ formatCount(value) }}</p>
            </div>
        </div>

        <!-- CONTROLS -->
        <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="ks-anim flex flex-1 items-center gap-2 border-2 border-ink bg-garlic px-3 py-2.5">
                <SearchIcon :size="18" :stroke-width="2.4" class="shrink-0 text-ink/55" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search name, address, suburb or postcode"
                    class="w-full bg-transparent outline-none placeholder:text-ink/40"
                    aria-label="Search the register"
                />
            </div>

            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="option in [
                        { key: 'status', value: 'published', label: 'Published' },
                        { key: 'status', value: 'draft', label: 'Draft' },
                        { key: 'rated', value: 'rated', label: 'Rated' },
                        { key: 'rated', value: 'unrated', label: 'Unrated' },
                        { key: 'certified', value: 'yes', label: 'Certified' },
                    ]"
                    :key="`${option.key}-${option.value}`"
                    type="button"
                    class="ks-anim border-2 border-ink px-2.5 py-2 transition-colors"
                    :class="filters[option.key] === option.value ? 'bg-ink text-garlic' : 'bg-garlic hover:bg-cream-deep'"
                    :aria-pressed="filters[option.key] === option.value"
                    @click="setFilter(option.key, option.value)"
                >
                    <span class="label-caps">{{ option.label }}</span>
                </button>
            </div>
        </div>

        <!-- TABLE -->
        <div class="mt-4 overflow-x-auto border-2 border-ink bg-garlic">
            <table class="w-full min-w-[900px] border-collapse text-sm">
                <thead>
                    <tr class="border-b-2 border-ink bg-cream-deep text-left">
                        <th v-for="column in columns" :key="column.key" class="px-3 py-2.5">
                            <button
                                v-if="column.sortable"
                                type="button"
                                class="ks-anim inline-flex items-center gap-1"
                                @click="sortBy(column.key)"
                            >
                                <span class="label-caps">{{ column.label }}</span>
                                <ChevronDownIcon
                                    v-if="filters.sort === column.key"
                                    :size="12"
                                    :stroke-width="3"
                                    :class="filters.direction === 'asc' ? 'rotate-180' : ''"
                                />
                            </button>
                            <span v-else class="label-caps">{{ column.label }}</span>
                        </th>
                        <th class="px-3 py-2.5"><span class="sr-only">Edit</span></th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="restaurant in restaurants.data"
                        :key="restaurant.id"
                        class="border-b border-ink/10 transition-colors last:border-b-0 hover:bg-cream"
                    >
                        <td class="px-3 py-2.5">
                            <Link :href="restaurant.edit_url" class="font-semibold ks-link-underline">
                                {{ restaurant.name }}
                            </Link>
                            <span class="block text-xs text-ink/50">{{ restaurant.suburb }}</span>
                        </td>

                        <td class="px-3 py-2.5">
                            <span class="flex items-center gap-2">
                                <span class="font-display font-black tabular-nums" :style="{ color: restaurant.tier.colour }">
                                    {{ restaurant.kebab_rating?.toFixed(1) ?? '—' }}
                                </span>
                                <StarRating
                                    :rating="restaurant.kebab_rating"
                                    :size="11"
                                    :colour="restaurant.tier.colour"
                                />
                            </span>
                        </td>

                        <td class="px-3 py-2.5 tabular-nums">{{ restaurant.google_rating ?? '—' }}</td>
                        <td class="px-3 py-2.5 tabular-nums text-ink/60">
                            {{ restaurant.google_review_count ? formatCount(restaurant.google_review_count) : '—' }}
                        </td>

                        <td class="px-3 py-2.5">
                            <span
                                class="label-caps inline-block border px-1.5 py-1"
                                :class="
                                    restaurant.status === 'published'
                                        ? 'border-lettuce bg-lettuce/10 text-lettuce'
                                        : 'border-ink/30 bg-cream-deep text-ink/60'
                                "
                            >
                                {{ restaurant.status }}
                            </span>
                            <CheckIcon
                                v-if="restaurant.society_approved"
                                :size="14"
                                :stroke-width="2.6"
                                class="ml-1.5 inline text-tomato"
                                title="Society Certified"
                            />
                        </td>

                        <td class="px-3 py-2.5 tabular-nums" :class="restaurant.photos_count ? '' : 'text-ink/35'">
                            {{ restaurant.photos_count }}
                        </td>

                        <td class="px-3 py-2.5 text-xs text-ink/50">{{ restaurant.updated_at }}</td>

                        <td class="px-3 py-2.5 text-right">
                            <Link
                                :href="restaurant.edit_url"
                                class="ks-anim inline-block border-2 border-ink bg-cream px-2.5 py-1.5 transition-colors hover:bg-ink hover:text-garlic"
                            >
                                <span class="label-caps">Edit</span>
                            </Link>
                        </td>
                    </tr>

                    <tr v-if="!restaurants.data.length">
                        <td colspan="8" class="px-3 py-10 text-center text-ink/55">
                            No restaurants match those criteria.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <nav v-if="restaurants.last_page > 1" class="mt-4 flex flex-wrap items-center gap-1.5" aria-label="Pagination">
            <Link
                v-for="link in restaurants.links"
                :key="link.label"
                :href="link.url ?? ''"
                preserve-scroll
                preserve-state
                class="ks-anim border-2 px-3 py-2 transition-colors"
                :class="[
                    link.active ? 'border-ink bg-ink text-garlic' : 'border-ink bg-garlic hover:bg-cream-deep',
                    link.url ? '' : 'pointer-events-none opacity-35',
                ]"
            >
                {{ pageLabel(link.label) }}
            </Link>
        </nav>
    </AdminLayout>
</template>
