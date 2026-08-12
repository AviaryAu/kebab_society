<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import ResourceTable from '../../../Components/Admin/ResourceTable.vue';

const props = defineProps({
    imports: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    options: { type: Object, required: true },
});

const columns = [
    { key: 'select', label: '', sortable: false },
    { key: 'proposed_title', label: 'Event', sortable: true },
    { key: 'proposed_start', label: 'When', sortable: true },
    { key: 'source', label: 'Source', sortable: false },
    { key: 'match_confidence', label: 'Match', sortable: true },
    { key: 'actions', label: '', sortable: false },
];

const filterOptions = [
    { key: 'status', value: 'pending', label: 'Awaiting review' },
    { key: 'status', value: 'approved', label: 'Approved' },
    { key: 'status', value: 'rejected', label: 'Rejected' },
    { key: 'status', value: 'auto', label: 'Automatic' },
    { key: 'confidence', value: 'unsure', label: 'Unsure' },
];

const selected = ref([]);

const pendingIds = computed(() =>
    props.imports.data.filter((item) => item.status === 'pending').map((item) => item.id),
);

const allSelected = computed(
    () => pendingIds.value.length > 0 && selected.value.length === pendingIds.value.length,
);

function toggleAll() {
    selected.value = allSelected.value ? [] : [...pendingIds.value];
}

function approve(id) {
    router.post(`/admin/imports/${id}/approve`, {}, { preserveScroll: true });
}

function reject(id) {
    router.post(`/admin/imports/${id}/reject`, {}, { preserveScroll: true });
}

function bulk(action) {
    if (! selected.value.length) {
        return;
    }

    const verb = action === 'approve' ? 'Publish' : 'Reject';

    if (! window.confirm(`${verb} ${selected.value.length} selected import(s)?`)) {
        return;
    }

    router.post(
        '/admin/imports/bulk',
        { action, ids: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
            },
        },
    );
}

function confidenceLabel(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${Math.round(value * 100)}%`;
}
</script>

<template>
    <Head>
        <title>Imports — Admin</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout title="Imports">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink/55">
                Events we were told about. Approving one publishes a draft with our own copy and a link
                back to the source.
            </p>

            <div v-if="selected.length" class="flex gap-2">
                <button
                    type="button"
                    class="ks-anim label-caps border-2 border-ink bg-ink px-3 py-2 text-garlic transition-colors hover:bg-garlic hover:text-ink"
                    @click="bulk('approve')"
                >
                    Publish {{ selected.length }}
                </button>
                <button
                    type="button"
                    class="ks-anim label-caps border-2 border-alert px-3 py-2 text-alert transition-colors hover:bg-alert hover:text-garlic"
                    @click="bulk('reject')"
                >
                    Reject {{ selected.length }}
                </button>
            </div>
        </div>

        <ResourceTable
            base-url="/admin/imports"
            :paginator="imports"
            :filters="filters"
            :filter-options="filterOptions"
            :columns="columns"
            :summary="summary"
            search-placeholder="Search title, venue or suburb"
            empty-message="Nothing waiting for review."
        >
            <template #row="{ item }">
                <td class="px-3 py-2.5">
                    <input
                        v-if="item.status === 'pending'"
                        v-model="selected"
                        type="checkbox"
                        :value="item.id"
                    />
                </td>

                <td class="px-3 py-2.5">
                    <p class="font-semibold">{{ item.title }}</p>
                    <p class="text-xs text-ink/50">
                        {{ item.venue || 'Unknown venue' }}<span v-if="item.suburb">, {{ item.suburb }}</span>
                    </p>
                    <a
                        v-if="item.source_url"
                        :href="item.source_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-xs text-ink/45 hover:text-ink"
                    >
                        View original
                    </a>
                </td>

                <td class="px-3 py-2.5">
                    <p>{{ item.day }}</p>
                    <p class="label-time text-xs text-ink/50">{{ item.time }}</p>
                </td>

                <td class="px-3 py-2.5 text-ink/70">{{ item.source }}</td>

                <td class="px-3 py-2.5">
                    <span
                        class="label-caps"
                        :class="item.confidence !== null && item.confidence < 0.9 ? 'text-alert' : 'text-ink/55'"
                    >
                        {{ confidenceLabel(item.confidence) }}
                    </span>
                    <Link
                        v-if="item.event_id"
                        :href="`/admin/events?search=${encodeURIComponent(item.title)}`"
                        class="block text-xs text-ink/45 hover:text-ink"
                    >
                        Matches an existing event
                    </Link>
                </td>

                <td class="px-3 py-2.5 text-right">
                    <div v-if="item.status === 'pending'" class="flex justify-end gap-1.5">
                        <button
                            type="button"
                            class="ks-anim label-caps border border-ink px-2 py-1 transition-colors hover:bg-ink hover:text-garlic"
                            @click="approve(item.id)"
                        >
                            Publish
                        </button>
                        <button
                            type="button"
                            class="ks-anim label-caps border border-ink/30 px-2 py-1 text-ink/60 transition-colors hover:border-alert hover:text-alert"
                            @click="reject(item.id)"
                        >
                            Reject
                        </button>
                    </div>
                    <span v-else class="label-caps text-ink/45">{{ item.status_label }}</span>
                </td>
            </template>
        </ResourceTable>

        <div v-if="pendingIds.length" class="mt-3">
            <button type="button" class="label-caps text-ink/55 hover:text-ink" @click="toggleAll">
                {{ allSelected ? 'Clear selection' : `Select all ${pendingIds.length} on this page` }}
            </button>
        </div>
    </AdminLayout>
</template>
