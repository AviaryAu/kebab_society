<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import ResourceTable from '../../../Components/Admin/ResourceTable.vue';

defineProps({
    venues: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const columns = [
    { key: 'name', label: 'Venue', sortable: true },
    { key: 'suburb', label: 'Suburb', sortable: true },
    { key: 'events', label: 'Events', sortable: false },
    { key: 'map', label: 'On map', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'updated_at', label: 'Updated', sortable: true },
];

const filterOptions = [
    { key: 'status', value: 'published', label: 'Published' },
    { key: 'status', value: 'draft', label: 'Draft' },
];
</script>

<template>
    <Head>
        <title>Venues — Admin</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout title="Venues">
        <div class="mb-4 flex items-center justify-between gap-3">
            <p class="text-sm text-ink/55">The rooms, stages and yards Sydney turns up to.</p>
            <Link
                href="/admin/venues/create"
                class="ks-anim label-caps border-2 border-ink bg-ink px-3 py-2 text-garlic transition-colors hover:bg-garlic hover:text-ink"
            >
                New venue
            </Link>
        </div>

        <ResourceTable
            base-url="/admin/venues"
            :paginator="venues"
            :filters="filters"
            :filter-options="filterOptions"
            :columns="columns"
            :summary="summary"
            search-placeholder="Search name, suburb or address"
            empty-message="No venues match those criteria."
        >
            <template #row="{ item }">
                <td class="px-3 py-2.5">
                    <Link :href="item.edit_url" class="ks-link font-semibold">{{ item.name }}</Link>
                    <span v-if="item.featured" class="label-caps ml-2 border border-ink bg-butter px-1 py-0.5">
                        Featured
                    </span>
                    <a
                        :href="item.public_url"
                        target="_blank"
                        rel="noopener"
                        class="block text-xs text-ink/45 hover:text-ink"
                    >
                        {{ item.public_url }}
                    </a>
                </td>

                <td class="px-3 py-2.5 text-ink/70">{{ item.suburb }}</td>
                <td class="px-3 py-2.5 tabular-nums" :class="item.events_count ? '' : 'text-ink/35'">
                    {{ item.events_count }}
                </td>

                <td class="px-3 py-2.5">
                    <span v-if="item.has_coordinates" class="label-caps text-ink/60">Yes</span>
                    <span v-else class="label-caps text-alert">Missing</span>
                </td>

                <td class="px-3 py-2.5">
                    <span
                        class="label-caps inline-block border px-1.5 py-1"
                        :class="
                            item.status === 'published'
                                ? 'border-ink bg-seafoam text-ink'
                                : 'border-ink/30 bg-cream-deep text-ink/60'
                        "
                    >
                        {{ item.status }}
                    </span>
                </td>

                <td class="px-3 py-2.5 text-xs text-ink/50">{{ item.updated_at }}</td>
            </template>
        </ResourceTable>
    </AdminLayout>
</template>
