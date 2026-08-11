<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import ResourceTable from '../../../Components/Admin/ResourceTable.vue';

const props = defineProps({
    events: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

const columns = [
    { key: 'title', label: 'Event', sortable: true },
    { key: 'start_datetime', label: 'Starts', sortable: true },
    { key: 'venue', label: 'Venue', sortable: false },
    { key: 'suburb', label: 'Suburb', sortable: true },
    { key: 'category', label: 'Category', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'updated_at', label: 'Updated', sortable: true },
];

const filterOptions = [
    { key: 'when', value: 'upcoming', label: 'Upcoming' },
    { key: 'when', value: 'past', label: 'Past' },
    { key: 'status', value: 'published', label: 'Published' },
    { key: 'status', value: 'draft', label: 'Draft' },
    ...props.categories.map((category) => ({ key: 'category', value: category.slug, label: category.name })),
];
</script>

<template>
    <Head>
        <title>Events — Admin</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout title="Events">
        <div class="mb-4 flex items-center justify-between gap-3">
            <p class="text-sm text-ink/55">Everything happening, and everything still to announce.</p>
            <Link
                href="/admin/events/create"
                class="ks-anim label-caps border-2 border-ink bg-ink px-3 py-2 text-garlic transition-colors hover:bg-garlic hover:text-ink"
            >
                New event
            </Link>
        </div>

        <ResourceTable
            base-url="/admin/events"
            :paginator="events"
            :filters="filters"
            :filter-options="filterOptions"
            :columns="columns"
            :summary="summary"
            search-placeholder="Search title, suburb or description"
            empty-message="No events match those criteria."
        >
            <template #row="{ item }">
                <td class="px-3 py-2.5">
                    <Link :href="item.edit_url" class="ks-link font-semibold">{{ item.title }}</Link>
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

                <td class="px-3 py-2.5 whitespace-nowrap" :class="item.is_past ? 'text-ink/40' : ''">
                    {{ item.starts }}
                </td>

                <td class="px-3 py-2.5" :class="item.venue ? '' : 'text-alert'">{{ item.venue ?? 'No venue' }}</td>
                <td class="px-3 py-2.5 text-ink/70">{{ item.suburb }}</td>
                <td class="px-3 py-2.5 text-ink/70">{{ item.category }}</td>

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
