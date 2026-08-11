<script setup>
/**
 * The shared list screen for events, venues and pages.
 *
 * Search, filter chips, sortable headers and pagination behave identically on
 * every resource; only the columns and cells differ, so those come in as slots.
 */
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SearchIcon from '../Icons/SearchIcon.vue';
import ChevronDownIcon from '../Icons/ChevronDownIcon.vue';

const props = defineProps({
    baseUrl: { type: String, required: true },
    paginator: { type: Object, required: true },
    filters: { type: Object, required: true },
    filterOptions: { type: Array, default: () => [] },
    columns: { type: Array, required: true },
    summary: { type: Object, default: null },
    searchPlaceholder: { type: String, default: 'Search' },
    emptyMessage: { type: String, default: 'Nothing matches those criteria.' },
});

const SEARCH_DEBOUNCE_MS = 300;

const search = ref(props.filters.search ?? '');
let timer = null;

function query(changes = {}) {
    const next = { ...props.filters, search: search.value || undefined, ...changes };

    router.get(
        props.baseUrl,
        Object.fromEntries(Object.entries(next).filter(([, value]) => value !== undefined && value !== null && value !== '')),
        { preserveState: true, preserveScroll: true, replace: true },
    );
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
    <div v-if="summary" class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div v-for="(value, key) in summary" :key="key" class="border-2 border-ink bg-garlic p-3">
            <p class="label-caps text-ink/45">{{ String(key).replace('_', ' ') }}</p>
            <p class="mt-1 font-display text-2xl tabular-nums">{{ value }}</p>
        </div>
    </div>

    <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="flex flex-1 items-center gap-2 border-2 border-ink bg-garlic px-3 py-2.5">
            <SearchIcon :size="18" :stroke-width="2.4" class="shrink-0 text-ink/55" />
            <input
                v-model="search"
                type="search"
                :placeholder="searchPlaceholder"
                class="w-full bg-transparent outline-none placeholder:text-ink/40"
                :aria-label="searchPlaceholder"
            />
        </div>

        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="option in filterOptions"
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

    <div class="mt-4 overflow-x-auto border-2 border-ink bg-garlic">
        <table class="w-full min-w-[880px] border-collapse text-sm">
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
                    v-for="item in paginator.data"
                    :key="item.id"
                    class="border-b border-ink/10 transition-colors last:border-b-0 hover:bg-cream"
                >
                    <slot name="row" :item="item" />

                    <td class="px-3 py-2.5 text-right">
                        <Link
                            :href="item.edit_url"
                            class="ks-anim inline-block border-2 border-ink bg-cream px-2.5 py-1.5 transition-colors hover:bg-ink hover:text-garlic"
                        >
                            <span class="label-caps">Edit</span>
                        </Link>
                    </td>
                </tr>

                <tr v-if="!paginator.data.length">
                    <td :colspan="columns.length + 1" class="px-3 py-10 text-center text-ink/55">
                        {{ emptyMessage }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <nav v-if="paginator.last_page > 1" class="mt-4 flex flex-wrap items-center gap-1.5" aria-label="Pagination">
        <Link
            v-for="link in paginator.links"
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
</template>
