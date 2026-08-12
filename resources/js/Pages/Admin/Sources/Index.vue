<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import ResourceTable from '../../../Components/Admin/ResourceTable.vue';

defineProps({
    sources: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    options: { type: Object, required: true },
});

const columns = [
    { key: 'name', label: 'Source', sortable: true },
    { key: 'trust', label: 'Trust', sortable: true },
    { key: 'permissions', label: 'Permissions', sortable: false },
    { key: 'schedule', label: 'Every', sortable: false },
    { key: 'last_run_at', label: 'Last run', sortable: true },
    { key: 'health', label: 'Health', sortable: false },
];

const filterOptions = [
    { key: 'health', value: 'healthy', label: 'Healthy' },
    { key: 'health', value: 'failing', label: 'Failing' },
    { key: 'health', value: 'disabled', label: 'Disabled' },
    { key: 'trust', value: 'licensed', label: 'Licensed' },
    { key: 'trust', value: 'verified', label: 'Venue' },
    { key: 'trust', value: 'signal', label: 'Editorial' },
];

function frequency(minutes) {
    if (minutes % 1440 === 0) {
        const days = minutes / 1440;

        return days === 1 ? 'Day' : `${days} days`;
    }

    if (minutes % 60 === 0) {
        const hours = minutes / 60;

        return hours === 1 ? 'Hour' : `${hours} hrs`;
    }

    return `${minutes} min`;
}
</script>

<template>
    <Head>
        <title>Sources — Admin</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout title="Sources">
        <div class="mb-4 flex items-center justify-between gap-3">
            <p class="text-sm text-ink/55">
                Where events come from. Editorial listings contribute facts and a link only.
            </p>
            <Link
                href="/admin/sources/create"
                class="ks-anim label-caps border-2 border-ink bg-ink px-3 py-2 text-garlic transition-colors hover:bg-garlic hover:text-ink"
            >
                New source
            </Link>
        </div>

        <ResourceTable
            base-url="/admin/sources"
            :paginator="sources"
            :filters="filters"
            :filter-options="filterOptions"
            :columns="columns"
            :summary="summary"
            search-placeholder="Search source name"
            empty-message="No sources match those criteria."
        >
            <template #row="{ item }">
                <td class="px-3 py-2.5">
                    <Link :href="item.edit_url" class="ks-link font-semibold">{{ item.name }}</Link>
                    <span class="block text-xs text-ink/45">{{ item.adapter }} · {{ item.tier }}</span>
                </td>

                <td class="px-3 py-2.5">
                    <span
                        class="label-caps inline-block border px-1.5 py-1"
                        :class="{
                            'border-ink bg-seafoam text-ink': item.trust_value === 'licensed',
                            'border-ink bg-butter text-ink': item.trust_value === 'verified',
                            'border-ink/30 bg-cream-deep text-ink/60': item.trust_value === 'signal',
                        }"
                    >
                        {{ item.trust }}
                    </span>
                </td>

                <td class="px-3 py-2.5">
                    <span class="label-caps" :class="item.auto_publish ? 'text-ink/60' : 'text-ink/35'">
                        {{ item.auto_publish ? 'Auto-publish' : 'Review' }}
                    </span>
                    <span class="label-caps block" :class="item.imports_images ? 'text-ink/60' : 'text-ink/35'">
                        {{ item.imports_images ? 'Images' : 'No images' }}
                    </span>
                </td>

                <td class="px-3 py-2.5 text-ink/70">{{ frequency(item.frequency_minutes) }}</td>

                <td class="px-3 py-2.5 text-xs text-ink/50">
                    {{ item.last_run_at || 'Never' }}
                    <span v-if="item.last_message" class="block text-ink/40">{{ item.last_message }}</span>
                </td>

                <td class="px-3 py-2.5">
                    <span v-if="! item.is_enabled" class="label-caps text-ink/40">Disabled</span>
                    <span v-else-if="! item.is_healthy" class="label-caps text-alert">
                        {{ item.consecutive_failures }} failures
                    </span>
                    <span v-else class="label-caps text-ink/60">OK</span>
                </td>
            </template>
        </ResourceTable>
    </AdminLayout>
</template>
