<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import ArrowRightIcon from '../../../Components/Icons/ArrowRightIcon.vue';

const props = defineProps({
    source: { type: Object, default: null },
    runs: { type: Array, default: () => [] },
    options: { type: Object, required: true },
});

const isNew = computed(() => props.source === null);

const form = useForm({
    name: props.source?.name ?? '',
    slug: props.source?.slug ?? '',
    adapter: props.source?.adapter ?? props.options.adapters[0] ?? '',
    tier: props.source?.tier ?? 'api',
    trust: props.source?.trust ?? 'licensed',
    endpoint: props.source?.endpoint ?? '',
    sitemap_url: props.source?.sitemap_url ?? '',
    website: props.source?.website ?? '',
    credentials: {},
    options: props.source?.options ?? {},
    default_category_slug: props.source?.default_category_slug ?? '',
    frequency_minutes: props.source?.frequency_minutes ?? 360,
    rate_limit_per_minute: props.source?.rate_limit_per_minute ?? 30,
    auto_publish: props.source?.auto_publish ?? false,
    allow_image_import: props.source?.allow_image_import ?? false,
    is_enabled: props.source?.is_enabled ?? true,
    licence: props.source?.licence ?? '',
    terms_url: props.source?.terms_url ?? '',
    notes: props.source?.notes ?? '',
});

const apiKey = ref('');

watch(apiKey, (value) => {
    form.credentials = value ? { api_key: value } : {};
});

const slugTouched = ref(! isNew.value);

function slugify(value) {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

watch(
    () => form.name,
    (name) => {
        if (! slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

// Mirrors the server-side rule: the tier decides what a source may ever do, so
// the form should not let someone tick a box the request will only reject.
const selectedTrust = computed(
    () => props.options.trusts.find((trust) => trust.value === form.trust) ?? null,
);

watch(selectedTrust, (trust) => {
    if (! trust) {
        return;
    }

    if (! trust.allows_images) {
        form.allow_image_import = false;
    }

    if (! trust.allows_auto_publish) {
        form.auto_publish = false;
    }
});

const optionsJson = ref(JSON.stringify(props.source?.options ?? {}, null, 2));
const optionsError = ref('');

watch(optionsJson, (value) => {
    try {
        form.options = value.trim() ? JSON.parse(value) : {};
        optionsError.value = '';
    } catch (error) {
        optionsError.value = error.message;
    }
});

function submit() {
    if (isNew.value) {
        form.post('/admin/sources');

        return;
    }

    form.put(`/admin/sources/${props.source.slug}`, { preserveScroll: true });
}

function runNow(dryRun) {
    router.post(
        `/admin/sources/${props.source.slug}/run`,
        { dry_run: dryRun, limit: 50 },
        { preserveScroll: true },
    );
}

function destroy() {
    if (! window.confirm(`Delete "${props.source.name}"? Events already imported will be kept.`)) {
        return;
    }

    router.delete(`/admin/sources/${props.source.slug}`);
}
</script>

<template>
    <Head>
        <title>{{ isNew ? 'New source' : source.name }} — Admin</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout :title="isNew ? 'New source' : source.name">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Link href="/admin/sources" class="ks-anim label-caps inline-flex items-center gap-1.5 text-ink/55">
                <ArrowRightIcon :size="14" :stroke-width="2.4" class="rotate-180" />
                All sources
            </Link>

            <div v-if="! isNew" class="ml-auto flex gap-2">
                <button
                    type="button"
                    class="ks-anim label-caps border-2 border-ink px-3 py-2 transition-colors hover:bg-cream-deep"
                    @click="runNow(true)"
                >
                    Dry run
                </button>
                <button
                    type="button"
                    class="ks-anim label-caps border-2 border-ink bg-ink px-3 py-2 text-garlic transition-colors hover:bg-garlic hover:text-ink"
                    @click="runNow(false)"
                >
                    Run now
                </button>
            </div>
        </div>

        <form class="grid gap-5 xl:grid-cols-[1fr_340px]" @submit.prevent="submit">
            <div class="space-y-5">
                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Identity</h2>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="label-caps text-ink/55">Name</span>
                            <input v-model="form.name" type="text" class="ks-field" required />
                            <span v-if="form.errors.name" class="ks-error">{{ form.errors.name }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Slug</span>
                            <input
                                v-model="form.slug"
                                type="text"
                                class="ks-field"
                                required
                                @input="slugTouched = true"
                            />
                            <span v-if="form.errors.slug" class="ks-error">{{ form.errors.slug }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Adapter</span>
                            <select v-model="form.adapter" class="ks-field" required>
                                <option v-for="adapter in options.adapters" :key="adapter" :value="adapter">
                                    {{ adapter }}
                                </option>
                            </select>
                            <span v-if="form.errors.adapter" class="ks-error">{{ form.errors.adapter }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Tier</span>
                            <select v-model="form.tier" class="ks-field" required>
                                <option v-for="tier in options.tiers" :key="tier.value" :value="tier.value">
                                    {{ tier.label }}
                                </option>
                            </select>
                            <span v-if="form.errors.tier" class="ks-error">{{ form.errors.tier }}</span>
                        </label>
                    </div>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Endpoints</h2>

                    <div class="mt-3 space-y-4">
                        <label class="block">
                            <span class="label-caps text-ink/55">API endpoint</span>
                            <input v-model="form.endpoint" type="url" class="ks-field" />
                            <span v-if="form.errors.endpoint" class="ks-error">{{ form.errors.endpoint }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Sitemap URL</span>
                            <input v-model="form.sitemap_url" type="url" class="ks-field" />
                            <span v-if="form.errors.sitemap_url" class="ks-error">
                                {{ form.errors.sitemap_url }}
                            </span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Website</span>
                            <input v-model="form.website" type="url" class="ks-field" />
                            <span v-if="form.errors.website" class="ks-error">{{ form.errors.website }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">API key</span>
                            <input
                                v-model="apiKey"
                                type="password"
                                autocomplete="off"
                                class="ks-field"
                                :placeholder="source?.has_credentials ? 'Stored — leave blank to keep' : ''"
                            />
                            <span class="mt-1 block text-xs text-ink/45">
                                Encrypted at rest and never sent back to this form.
                            </span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Adapter options (JSON)</span>
                            <textarea v-model="optionsJson" rows="6" class="ks-field font-mono text-xs" />
                            <span v-if="optionsError" class="ks-error">{{ optionsError }}</span>
                        </label>
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Permissions</h2>

                    <label class="mt-3 block">
                        <span class="label-caps text-ink/55">Trust</span>
                        <select v-model="form.trust" class="ks-field" required>
                            <option v-for="trust in options.trusts" :key="trust.value" :value="trust.value">
                                {{ trust.label }}
                            </option>
                        </select>
                        <span v-if="form.errors.trust" class="ks-error">{{ form.errors.trust }}</span>
                    </label>

                    <p
                        v-if="selectedTrust && ! selectedTrust.allows_images"
                        class="mt-3 border-l-2 border-ink/30 bg-cream-deep px-3 py-2 text-xs text-ink/65"
                    >
                        Editorial listings give us facts and a link. Their artwork and wording stay theirs, so
                        images and auto-publishing are unavailable for this tier.
                    </p>

                    <label class="mt-4 flex items-center gap-2">
                        <input
                            v-model="form.auto_publish"
                            type="checkbox"
                            :disabled="selectedTrust && ! selectedTrust.allows_auto_publish"
                        />
                        <span class="label-caps text-ink/70">Publish without review</span>
                    </label>
                    <span v-if="form.errors.auto_publish" class="ks-error">{{ form.errors.auto_publish }}</span>

                    <label class="mt-2 flex items-center gap-2">
                        <input
                            v-model="form.allow_image_import"
                            type="checkbox"
                            :disabled="selectedTrust && ! selectedTrust.allows_images"
                        />
                        <span class="label-caps text-ink/70">Import hero images</span>
                    </label>
                    <span v-if="form.errors.allow_image_import" class="ks-error">
                        {{ form.errors.allow_image_import }}
                    </span>

                    <label class="mt-2 flex items-center gap-2">
                        <input v-model="form.is_enabled" type="checkbox" />
                        <span class="label-caps text-ink/70">Enabled</span>
                    </label>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Schedule</h2>

                    <label class="mt-3 block">
                        <span class="label-caps text-ink/55">Every (minutes)</span>
                        <input
                            v-model.number="form.frequency_minutes"
                            type="number"
                            :min="options.min_frequency_minutes"
                            class="ks-field"
                            required
                        />
                        <span class="mt-1 block text-xs text-ink/45">
                            Minimum {{ options.min_frequency_minutes }} minutes.
                        </span>
                        <span v-if="form.errors.frequency_minutes" class="ks-error">
                            {{ form.errors.frequency_minutes }}
                        </span>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Default category</span>
                        <select v-model="form.default_category_slug" class="ks-field">
                            <option value="">None</option>
                            <option v-for="category in options.categories" :key="category.slug" :value="category.slug">
                                {{ category.name }}
                            </option>
                        </select>
                    </label>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Permission record</h2>

                    <label class="mt-3 block">
                        <span class="label-caps text-ink/55">Licence</span>
                        <input v-model="form.licence" type="text" class="ks-field" />
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Terms URL</span>
                        <input v-model="form.terms_url" type="url" class="ks-field" />
                        <span v-if="form.errors.terms_url" class="ks-error">{{ form.errors.terms_url }}</span>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Notes</span>
                        <textarea v-model="form.notes" rows="3" class="ks-field" />
                    </label>
                </section>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="ks-anim label-caps border-2 border-ink bg-ink px-4 py-2.5 text-garlic transition-colors hover:bg-garlic hover:text-ink"
                        :disabled="form.processing"
                    >
                        {{ isNew ? 'Create source' : 'Save changes' }}
                    </button>

                    <button
                        v-if="! isNew"
                        type="button"
                        class="ks-anim label-caps border-2 border-alert px-4 py-2.5 text-alert transition-colors hover:bg-alert hover:text-garlic"
                        @click="destroy"
                    >
                        Delete
                    </button>
                </div>
            </aside>
        </form>

        <section v-if="! isNew && runs.length" class="mt-6 border-2 border-ink bg-garlic p-5">
            <h2 class="label-caps text-ink/45">Recent runs</h2>

            <table class="mt-3 w-full text-sm">
                <thead>
                    <tr class="label-caps border-b border-ink/20 text-left text-ink/45">
                        <th class="py-2">Started</th>
                        <th class="py-2">Status</th>
                        <th class="py-2 text-right">Seen</th>
                        <th class="py-2 text-right">New</th>
                        <th class="py-2 text-right">Updated</th>
                        <th class="py-2 text-right">Failed</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="run in runs" :key="run.id" class="border-b border-ink/10">
                        <td class="py-2 text-ink/70">
                            {{ new Date(run.started_at).toLocaleString('en-AU') }}
                            <span v-if="run.dry_run" class="label-caps ml-1 text-ink/40">dry</span>
                        </td>
                        <td class="py-2">
                            <span class="label-caps" :class="run.status === 'failed' ? 'text-alert' : 'text-ink/60'">
                                {{ run.status }}
                            </span>
                        </td>
                        <td class="py-2 text-right tabular-nums">{{ run.items_seen }}</td>
                        <td class="py-2 text-right tabular-nums">{{ run.items_created }}</td>
                        <td class="py-2 text-right tabular-nums">{{ run.items_updated }}</td>
                        <td class="py-2 text-right tabular-nums" :class="run.items_failed ? 'text-alert' : ''">
                            {{ run.items_failed }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AdminLayout>
</template>
