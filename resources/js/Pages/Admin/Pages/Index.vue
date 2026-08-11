<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import ResourceTable from '../../../Components/Admin/ResourceTable.vue';

defineProps({
    pages: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const columns = [
    { key: 'title', label: 'Page', sortable: true },
    { key: 'type', label: 'Type', sortable: true },
    { key: 'sort_order', label: 'Order', sortable: true },
    { key: 'body', label: 'Body', sortable: false },
    { key: 'published_at', label: 'Published', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'updated_at', label: 'Updated', sortable: true },
];

const filterOptions = [
    { key: 'type', value: 'guide', label: 'Guides' },
    { key: 'type', value: 'page', label: 'Pages' },
    { key: 'status', value: 'published', label: 'Published' },
    { key: 'status', value: 'draft', label: 'Draft' },
];
</script>

<template>
    <Head>
        <title>Pages — Admin</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout title="Pages & guides">
        <div class="mb-4 flex items-center justify-between gap-3">
            <p class="text-sm text-ink/55">Editorial guides and standalone pages, written in the editor.</p>
            <Link
                href="/admin/pages/create"
                class="ks-anim label-caps border-2 border-ink bg-ink px-3 py-2 text-garlic transition-colors hover:bg-garlic hover:text-ink"
            >
                New page
            </Link>
        </div>

        <ResourceTable
            base-url="/admin/pages"
            :paginator="pages"
            :filters="filters"
            :filter-options="filterOptions"
            :columns="columns"
            :summary="summary"
            search-placeholder="Search title or excerpt"
            empty-message="No pages match those criteria."
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

                <td class="px-3 py-2.5 text-ink/70 capitalize">{{ item.type }}</td>
                <td class="px-3 py-2.5 tabular-nums text-ink/60">{{ item.sort_order }}</td>

                <td class="px-3 py-2.5">
                    <span v-if="item.has_body" class="label-caps text-ink/60">Written</span>
                    <span v-else class="label-caps text-alert">Empty</span>
                </td>

                <td class="px-3 py-2.5 whitespace-nowrap text-ink/70">{{ item.published_at ?? '—' }}</td>

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
