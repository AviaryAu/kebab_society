<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import StarRating from '../../../Components/StarRating.vue';
import ArrowRightIcon from '../../../Components/Icons/ArrowRightIcon.vue';
import CloseIcon from '../../../Components/Icons/CloseIcon.vue';
import StarIcon from '../../../Components/Icons/StarIcon.vue';

const props = defineProps({
    restaurant: { type: Object, required: true },
    options: { type: Object, required: true },
});

const DAYS = [
    ['mon', 'Monday'],
    ['tue', 'Tuesday'],
    ['wed', 'Wednesday'],
    ['thu', 'Thursday'],
    ['fri', 'Friday'],
    ['sat', 'Saturday'],
    ['sun', 'Sunday'],
];

const form = useForm({
    name: props.restaurant.name,
    slug: props.restaurant.slug,
    description: props.restaurant.description ?? '',
    address_line: props.restaurant.address_line,
    suburb_id: props.restaurant.suburb_id,
    postcode: props.restaurant.postcode,
    latitude: props.restaurant.latitude,
    longitude: props.restaurant.longitude,
    phone: props.restaurant.phone ?? '',
    website: props.restaurant.website ?? '',
    google_place_id: props.restaurant.google_place_id ?? '',
    google_rating: props.restaurant.google_rating,
    google_review_count: props.restaurant.google_review_count,
    price_level: props.restaurant.price_level,
    status: props.restaurant.status,
    verification_status: props.restaurant.verification_status,
    society_approved: props.restaurant.society_approved,
    editorial_adjustment: props.restaurant.editorial_adjustment,
    editorial_note: props.restaurant.editorial_note ?? '',
    styles: [...props.restaurant.styles],
    opening_hours: Object.fromEntries(DAYS.map(([day]) => [day, props.restaurant.opening_hours?.[day] ?? []])),
});

const uploads = ref(null);
const uploadForm = useForm({ photos: [] });

const breakdown = computed(() => props.restaurant.rating_breakdown?.components ?? []);

function submit() {
    form.put(`/admin/restaurants/${props.restaurant.slug}`, { preserveScroll: true });
}

function toggleStyle(id) {
    form.styles = form.styles.includes(id) ? form.styles.filter((value) => value !== id) : [...form.styles, id];
}

function addSession(day) {
    form.opening_hours[day] = [...form.opening_hours[day], { open: '11:00', close: '22:00' }];
}

function removeSession(day, index) {
    form.opening_hours[day] = form.opening_hours[day].filter((_, position) => position !== index);
}

function copyMondayToAll() {
    const monday = form.opening_hours.mon;
    DAYS.forEach(([day]) => {
        form.opening_hours[day] = monday.map((session) => ({ ...session }));
    });
}

function onFilesSelected(event) {
    uploadForm.photos = Array.from(event.target.files ?? []);

    if (uploadForm.photos.length === 0) {
        return;
    }

    uploadForm.post(`/admin/restaurants/${props.restaurant.slug}/photos`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset('photos');
            if (uploads.value) {
                uploads.value.value = '';
            }
        },
    });
}

function makePrimary(photo) {
    router.patch(`/admin/photos/${photo.id}`, { is_primary: true, caption: photo.caption, credit: photo.credit }, {
        preserveScroll: true,
    });
}

function saveCaption(photo, caption) {
    router.patch(`/admin/photos/${photo.id}`, { caption, credit: photo.credit }, { preserveScroll: true });
}

function removePhoto(photo) {
    router.delete(`/admin/photos/${photo.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head>
        <title>{{ restaurant.name }} — Register</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout :title="restaurant.name">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Link href="/admin/restaurants" class="ks-anim label-caps inline-flex items-center gap-1.5 text-ink/55">
                <ArrowRightIcon :size="14" :stroke-width="2.4" class="rotate-180" />
                Back to the register
            </Link>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_380px]">
            <form class="space-y-5" @submit.prevent="submit">
                <!-- IDENTITY -->
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
                            <input v-model="form.slug" type="text" class="ks-field" required />
                            <span v-if="form.errors.slug" class="ks-error">{{ form.errors.slug }}</span>
                        </label>
                    </div>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Description</span>
                        <textarea v-model="form.description" rows="3" class="ks-field" />
                        <span v-if="form.errors.description" class="ks-error">{{ form.errors.description }}</span>
                    </label>
                </section>

                <!-- LOCATION -->
                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Location</h2>
                    <p v-if="restaurant.location_precision !== 'address'" class="mt-1 text-xs text-tomato">
                        Coordinates are suburb-level only. Refine them if you know the shopfront.
                    </p>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="label-caps text-ink/55">Street address</span>
                            <input v-model="form.address_line" type="text" class="ks-field" required />
                            <span v-if="form.errors.address_line" class="ks-error">{{ form.errors.address_line }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Suburb</span>
                            <select v-model.number="form.suburb_id" class="ks-field">
                                <option v-for="suburb in options.suburbs" :key="suburb.id" :value="suburb.id">
                                    {{ suburb.name }} — {{ suburb.region }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Postcode</span>
                            <input v-model="form.postcode" type="text" inputmode="numeric" class="ks-field" required />
                            <span v-if="form.errors.postcode" class="ks-error">{{ form.errors.postcode }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Latitude</span>
                            <input v-model.number="form.latitude" type="number" step="0.0000001" class="ks-field" />
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Longitude</span>
                            <input v-model.number="form.longitude" type="number" step="0.0000001" class="ks-field" />
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Phone</span>
                            <input v-model="form.phone" type="text" class="ks-field" />
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Website</span>
                            <input v-model="form.website" type="url" class="ks-field" placeholder="https://" />
                            <span v-if="form.errors.website" class="ks-error">{{ form.errors.website }}</span>
                        </label>
                    </div>
                </section>

                <!-- EVIDENCE -->
                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Evidence</h2>
                    <p class="mt-1 text-xs text-ink/50">
                        The published rating is derived from this. It cannot be typed in directly.
                    </p>

                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="label-caps text-ink/55">Google rating</span>
                            <input
                                v-model.number="form.google_rating"
                                type="number"
                                step="0.1"
                                min="0"
                                max="5"
                                class="ks-field"
                            />
                            <span v-if="form.errors.google_rating" class="ks-error">
                                {{ form.errors.google_rating }}
                            </span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Google reviews</span>
                            <input v-model.number="form.google_review_count" type="number" min="0" class="ks-field" />
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Price level</span>
                            <select v-model.number="form.price_level" class="ks-field">
                                <option :value="null">Unknown</option>
                                <option :value="1">$</option>
                                <option :value="2">$$</option>
                                <option :value="3">$$$</option>
                                <option :value="4">$$$$</option>
                            </select>
                        </label>

                        <label class="block sm:col-span-3">
                            <span class="label-caps text-ink/55">Google Place ID</span>
                            <input v-model="form.google_place_id" type="text" class="ks-field" />
                            <span v-if="form.errors.google_place_id" class="ks-error">
                                {{ form.errors.google_place_id }}
                            </span>
                        </label>
                    </div>
                </section>

                <!-- SOCIETY -->
                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">The Society's position</h2>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="label-caps text-ink/55">Status</span>
                            <select v-model="form.status" class="ks-field">
                                <option v-for="status in options.statuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Verification</span>
                            <select v-model="form.verification_status" class="ks-field">
                                <option
                                    v-for="status in options.verifications"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <label class="mt-4 flex items-start gap-3 border-2 border-ink bg-cream p-3">
                        <input v-model="form.society_approved" type="checkbox" class="mt-0.5 h-4 w-4 accent-tomato" />
                        <span>
                            <span class="label-caps">Society Certified</span>
                            <span class="mt-1 block text-xs text-ink/55">
                                Only tick this once an editor has visited in person. It puts the stamp on the map.
                            </span>
                        </span>
                    </label>

                    <div class="mt-4">
                        <label class="label-caps text-ink/55" for="adjustment">
                            Editorial adjustment
                            <span class="ml-1 tabular-nums text-tomato">
                                {{ form.editorial_adjustment > 0 ? '+' : '' }}{{ form.editorial_adjustment }}★
                            </span>
                        </label>
                        <input
                            id="adjustment"
                            v-model.number="form.editorial_adjustment"
                            type="range"
                            :min="-options.adjustment_limit"
                            :max="options.adjustment_limit"
                            step="0.1"
                            class="mt-2 w-full accent-tomato"
                        />
                        <p class="mt-1 text-xs text-ink/50">
                            Bounded at ±{{ options.adjustment_limit }} stars, and always disclosed on the public page.
                        </p>
                        <span v-if="form.errors.editorial_adjustment" class="ks-error">
                            {{ form.errors.editorial_adjustment }}
                        </span>
                    </div>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Editorial note</span>
                        <textarea v-model="form.editorial_note" rows="2" class="ks-field" />
                    </label>
                </section>

                <!-- STYLES -->
                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">What they serve</h2>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <button
                            v-for="style in options.styles"
                            :key="style.id"
                            type="button"
                            class="border-2 px-2.5 py-1.5 transition-colors"
                            :class="
                                form.styles.includes(style.id)
                                    ? 'border-ink bg-ink text-garlic'
                                    : 'border-ink/30 bg-cream hover:border-ink'
                            "
                            :aria-pressed="form.styles.includes(style.id)"
                            @click="toggleStyle(style.id)"
                        >
                            <span class="label-caps">{{ style.name }}</span>
                        </button>
                    </div>
                </section>

                <!-- HOURS -->
                <section class="border-2 border-ink bg-garlic p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="label-caps text-ink/45">Trading hours</h2>
                        <button type="button" class="label-caps text-tomato underline" @click="copyMondayToAll">
                            Copy Monday to all
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-ink/50">
                        A closing time earlier than the opening time means the shop trades past midnight.
                    </p>

                    <ul class="mt-3 space-y-2">
                        <li v-for="[day, label] in DAYS" :key="day" class="flex flex-wrap items-center gap-2">
                            <span class="w-24 shrink-0 text-sm font-semibold">{{ label }}</span>

                            <span
                                v-for="(session, index) in form.opening_hours[day]"
                                :key="index"
                                class="flex items-center gap-1"
                            >
                                <input v-model="session.open" type="time" class="ks-field w-28 !mt-0" />
                                <span class="text-ink/40">–</span>
                                <input v-model="session.close" type="time" class="ks-field w-28 !mt-0" />
                                <button
                                    type="button"
                                    class="ks-anim border-2 border-ink/25 p-1.5 text-ink/50 hover:border-tomato hover:text-tomato"
                                    :aria-label="`Remove ${label} session`"
                                    @click="removeSession(day, index)"
                                >
                                    <CloseIcon :size="12" :stroke-width="3" />
                                </button>
                            </span>

                            <button
                                type="button"
                                class="ks-anim border-2 border-ink/30 px-2 py-1.5 hover:border-ink"
                                @click="addSession(day)"
                            >
                                <span class="label-caps">{{ form.opening_hours[day].length ? '+ Session' : 'Add hours' }}</span>
                            </button>

                            <span v-if="!form.opening_hours[day].length" class="label-caps text-ink/35">Closed</span>
                        </li>
                    </ul>
                </section>

                <div class="sticky bottom-0 flex items-center gap-3 border-2 border-ink bg-cream p-3 stamped-sm">
                    <button
                        type="submit"
                        class="ks-anim border-2 border-ink bg-tomato px-5 py-2.5 text-garlic transition-colors hover:bg-tomato-deep disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <span class="label-caps">{{ form.processing ? 'Saving…' : 'Save changes' }}</span>
                    </button>
                    <span v-if="form.isDirty" class="label-caps text-tomato">Unsaved changes</span>
                </div>
            </form>

            <!-- SIDEBAR -->
            <div class="space-y-5">
                <section class="border-2 border-ink bg-cream p-5">
                    <h2 class="label-caps text-ink/45">Published rating</h2>
                    <div class="mt-3 flex items-end gap-3">
                        <p
                            class="font-display text-5xl font-black leading-none tabular-nums"
                            :style="{ color: restaurant.tier.colour }"
                        >
                            {{ restaurant.is_rated ? restaurant.kebab_rating.toFixed(1) : '—' }}
                        </p>
                        <div class="pb-1">
                            <StarRating :rating="restaurant.kebab_rating" :size="18" :colour="restaurant.tier.colour" />
                            <p class="label-caps mt-1.5" :style="{ color: restaurant.tier.colour }">
                                {{ restaurant.tier.label }}
                            </p>
                        </div>
                    </div>

                    <ul v-if="breakdown.length" class="mt-4 space-y-1.5 border-t-2 border-ink/10 pt-3">
                        <li
                            v-for="component in breakdown"
                            :key="component.key"
                            class="flex items-baseline justify-between gap-2 text-xs"
                        >
                            <span class="text-ink/60">{{ component.label }}</span>
                            <span class="tabular-nums font-semibold">
                                {{ component.rating.toFixed(2) }}★ · {{ Math.round(component.weight * 100) }}%
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-xs text-ink/50">
                        No ratings held. Add a Google rating to publish one.
                    </p>

                    <p class="mt-3 text-[11px] text-ink/40">Source: {{ restaurant.data_source_label }}</p>
                </section>

                <!-- PHOTOS -->
                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Photographs</h2>
                    <p class="mt-1 text-xs text-ink/50">
                        Resized automatically into thumbnail, card and hero formats.
                    </p>

                    <label
                        class="ks-anim mt-3 flex cursor-pointer flex-col items-center gap-1 border-2 border-dashed border-ink/40 bg-cream p-5 text-center transition-colors hover:border-ink hover:bg-cream-deep"
                    >
                        <StarIcon :size="20" :stroke-width="2" class="text-ink/40" />
                        <span class="label-caps">
                            {{ uploadForm.processing ? 'Uploading…' : 'Choose photographs' }}
                        </span>
                        <span class="text-xs text-ink/45">JPG, PNG, WebP or HEIC</span>
                        <input
                            ref="uploads"
                            type="file"
                            accept="image/*"
                            multiple
                            class="sr-only"
                            :disabled="uploadForm.processing"
                            @change="onFilesSelected"
                        />
                    </label>

                    <p v-if="uploadForm.errors['photos.0']" class="ks-error">{{ uploadForm.errors['photos.0'] }}</p>
                    <div v-if="uploadForm.progress" class="mt-2 h-2 w-full border-2 border-ink bg-cream">
                        <div class="h-full bg-tomato" :style="{ width: `${uploadForm.progress.percentage}%` }" />
                    </div>

                    <ul v-if="restaurant.photos.length" class="mt-4 space-y-3">
                        <li v-for="photo in restaurant.photos" :key="photo.id" class="border-2 border-ink bg-cream">
                            <img :src="photo.card" :alt="photo.caption || restaurant.name" class="h-32 w-full object-cover" />

                            <div class="space-y-2 p-2.5">
                                <input
                                    :value="photo.caption"
                                    type="text"
                                    placeholder="Caption"
                                    class="w-full border-2 border-ink/25 bg-garlic px-2 py-1.5 text-xs outline-none focus:border-ink"
                                    @change="saveCaption(photo, $event.target.value)"
                                />

                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="!photo.is_primary"
                                        type="button"
                                        class="ks-anim border-2 border-ink px-2 py-1 transition-colors hover:bg-ink hover:text-garlic"
                                        @click="makePrimary(photo)"
                                    >
                                        <span class="label-caps">Make lead</span>
                                    </button>
                                    <span v-else class="label-caps border-2 border-lettuce px-2 py-1 text-lettuce">
                                        Lead photo
                                    </span>

                                    <button
                                        type="button"
                                        class="ks-anim ml-auto border-2 border-ink/25 p-1.5 text-ink/50 transition-colors hover:border-tomato hover:text-tomato"
                                        aria-label="Delete photograph"
                                        @click="removePhoto(photo)"
                                    >
                                        <CloseIcon :size="13" :stroke-width="3" />
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <p v-else class="mt-4 text-sm text-ink/50">No photographs yet.</p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.ks-field {
    margin-top: 0.375rem;
    display: block;
    width: 100%;
    border: 2px solid var(--color-ink);
    background: var(--color-cream);
    padding: 0.5rem 0.75rem;
    outline: none;
}

.ks-field:focus {
    box-shadow: 3px 3px 0 var(--color-ink);
}

.ks-error {
    margin-top: 0.25rem;
    display: block;
    font-size: 0.75rem;
    color: var(--color-tomato);
}
</style>
