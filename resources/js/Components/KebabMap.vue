<script setup>
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import {
    GeolocateControl,
    LngLatBounds,
    Map as MapLibreMap,
    Marker,
    NavigationControl,
} from 'maplibre-gl';

/**
 * THE KEBAB MAP
 *
 * Sydney, covered in kebabs.
 *
 * Individual shops are drawn by a symbol layer using the Society's own marker
 * artwork (never a default map pin). At low zoom the points collapse into
 * clusters, which are rendered as DOM markers so the map needs no external
 * font/glyph service.
 */

const props = defineProps({
    restaurants: { type: Array, default: () => [] },
    config: { type: Object, required: true },
    selectedId: { type: [Number, String], default: null },
    nightMode: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'ready']);

const SOURCE_ID = 'kebab-shops';
const CLUSTER_HIT_LAYER = 'kebab-cluster-hitbox';
const POINT_LAYER = 'kebab-points';
const STAMP_LAYER = 'kebab-stamps';
const HALO_LAYER = 'kebab-selected-halo';

const MARKER_TIERS = ['legendary', 'excellent', 'good', 'average', 'questionable', 'unrated'];

const container = ref(null);
const map = shallowRef(null);
const isLoaded = ref(false);
const clusterMarkers = new Map();

function tileUrl() {
    const template = props.nightMode ? props.config.tiles.night : props.config.tiles.day;

    // CARTO uses {r} for the retina suffix, which MapLibre does not understand.
    return template.replace('{r}', window.devicePixelRatio > 1 ? '@2x' : '');
}

function baseStyle() {
    return {
        version: 8,
        sources: {
            basemap: {
                type: 'raster',
                tiles: [tileUrl()],
                tileSize: 256,
                attribution: props.config.attribution,
            },
        },
        layers: [{ id: 'basemap', type: 'raster', source: 'basemap' }],
    };
}

function toFeatureCollection(restaurants) {
    return {
        type: 'FeatureCollection',
        features: restaurants
            .filter((restaurant) => Number.isFinite(restaurant.longitude) && Number.isFinite(restaurant.latitude))
            .map((restaurant) => ({
                type: 'Feature',
                id: restaurant.id,
                geometry: {
                    type: 'Point',
                    coordinates: [Number(restaurant.longitude), Number(restaurant.latitude)],
                },
                properties: {
                    id: restaurant.id,
                    name: restaurant.name,
                    marker: `ks-marker-${restaurant.tier.marker}`,
                    approved: restaurant.society_approved ? 1 : 0,
                    rating: restaurant.kebab_rating ?? 0,
                },
            })),
    };
}

async function loadMarkerImages(instance) {
    const images = [
        ...MARKER_TIERS.map((tier) => [`ks-marker-${tier}`, `/images/markers/marker-${tier}.png`]),
        ['ks-stamp', '/images/brand/society-approved-stamp-sm.png'],
    ];

    await Promise.all(
        images.map(async ([id, url]) => {
            if (instance.hasImage(id)) {
                return;
            }

            try {
                const { data } = await instance.loadImage(url);
                if (!instance.hasImage(id)) {
                    instance.addImage(id, data, { pixelRatio: 2 });
                }
            } catch {
                // A missing sprite must not take the whole map down.
            }
        }),
    );
}

function addLayers(instance) {
    instance.addSource(SOURCE_ID, {
        type: 'geojson',
        data: toFeatureCollection(props.restaurants),
        cluster: true,
        clusterRadius: 52,
        clusterMaxZoom: 13,
        // Carried up so a cluster can advertise the best kebab hiding inside it.
        clusterProperties: { best: ['max', ['get', 'rating']] },
    });

    // Invisible circles give the DOM cluster badges something to be queried from.
    instance.addLayer({
        id: CLUSTER_HIT_LAYER,
        type: 'circle',
        source: SOURCE_ID,
        filter: ['has', 'point_count'],
        paint: { 'circle-radius': 1, 'circle-opacity': 0 },
    });

    // Highlight ring for the shop currently open in the preview dialog.
    instance.addLayer({
        id: HALO_LAYER,
        type: 'circle',
        source: SOURCE_ID,
        filter: ['==', ['get', 'id'], -1],
        paint: {
            'circle-radius': 22,
            'circle-color': '#c1121f',
            'circle-opacity': 0.18,
            'circle-stroke-width': 2,
            'circle-stroke-color': '#c1121f',
        },
    });

    instance.addLayer({
        id: POINT_LAYER,
        type: 'symbol',
        source: SOURCE_ID,
        filter: ['!', ['has', 'point_count']],
        layout: {
            'icon-image': ['get', 'marker'],
            'icon-anchor': 'bottom',
            'icon-allow-overlap': true,
            'icon-ignore-placement': true,
            'icon-size': ['interpolate', ['linear'], ['zoom'], 9, 0.32, 13, 0.5, 16, 0.72],
        },
    });

    instance.addLayer({
        id: STAMP_LAYER,
        type: 'symbol',
        source: SOURCE_ID,
        filter: ['all', ['!', ['has', 'point_count']], ['==', ['get', 'approved'], 1]],
        layout: {
            'icon-image': 'ks-stamp',
            'icon-anchor': 'bottom',
            'icon-allow-overlap': true,
            'icon-ignore-placement': true,
            'icon-offset': [26, 26],
            'icon-size': ['interpolate', ['linear'], ['zoom'], 9, 0.2, 13, 0.3, 16, 0.42],
        },
    });
}

function clusterElement(count, best) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'ks-cluster';
    element.setAttribute('aria-label', `${count} kebab shops. Zoom in.`);
    element.innerHTML = `<span class="ks-cluster__count">${count}</span><span class="ks-cluster__label">kebabs</span>`;

    if (best >= 4.5) {
        element.dataset.best = 'legendary';
    } else if (best >= 4) {
        element.dataset.best = 'excellent';
    }

    // Scale the badge with the size of the crowd it represents.
    const scale = Math.min(1.45, 0.85 + Math.log10(Math.max(count, 1)) * 0.4);
    element.style.setProperty('--ks-cluster-scale', scale.toFixed(2));

    return element;
}

function syncClusterMarkers() {
    const instance = map.value;

    if (!instance || !instance.getLayer(CLUSTER_HIT_LAYER)) {
        return;
    }

    const clusters = instance.querySourceFeatures(SOURCE_ID, { filter: ['has', 'point_count'] });
    const seen = new Set();

    clusters.forEach((cluster) => {
        const id = String(cluster.properties.cluster_id);

        // Tiles overlap, so the same cluster can come back more than once.
        if (seen.has(id)) {
            return;
        }

        seen.add(id);

        const coordinates = cluster.geometry.coordinates;

        if (clusterMarkers.has(id)) {
            clusterMarkers.get(id).setLngLat(coordinates);

            return;
        }

        const element = clusterElement(cluster.properties.point_count, Number(cluster.properties.best ?? 0));

        element.addEventListener('click', async () => {
            const zoom = await instance.getSource(SOURCE_ID).getClusterExpansionZoom(cluster.properties.cluster_id);

            instance.easeTo({ center: coordinates, zoom, duration: 600 });
        });

        clusterMarkers.set(id, new Marker({ element }).setLngLat(coordinates).addTo(instance));
    });

    clusterMarkers.forEach((marker, id) => {
        if (!seen.has(id)) {
            marker.remove();
            clusterMarkers.delete(id);
        }
    });
}

function findRestaurant(id) {
    return props.restaurants.find((restaurant) => String(restaurant.id) === String(id)) ?? null;
}

function registerInteractions(instance) {
    const onPointClick = (event) => {
        const feature = event.features?.[0];
        const restaurant = feature ? findRestaurant(feature.properties.id) : null;

        if (restaurant) {
            emit('select', restaurant);
        }
    };

    [POINT_LAYER, STAMP_LAYER].forEach((layer) => {
        instance.on('click', layer, onPointClick);
        instance.on('mouseenter', layer, () => {
            instance.getCanvas().style.cursor = 'pointer';
        });
        instance.on('mouseleave', layer, () => {
            instance.getCanvas().style.cursor = '';
        });
    });

    instance.on('move', syncClusterMarkers);
    instance.on('moveend', syncClusterMarkers);
    instance.on('idle', syncClusterMarkers);
    instance.on('sourcedata', (event) => {
        if (event.sourceId === SOURCE_ID && event.isSourceLoaded) {
            syncClusterMarkers();
        }
    });
}

function fitToRestaurants(restaurants = props.restaurants, options = {}) {
    const instance = map.value;
    const points = restaurants.filter((r) => Number.isFinite(r.latitude) && Number.isFinite(r.longitude));

    if (!instance || points.length === 0) {
        return;
    }

    if (points.length === 1) {
        instance.easeTo({ center: [points[0].longitude, points[0].latitude], zoom: 15, duration: 700 });
        return;
    }

    const bounds = points.reduce(
        (acc, restaurant) => acc.extend([Number(restaurant.longitude), Number(restaurant.latitude)]),
        new LngLatBounds(
            [Number(points[0].longitude), Number(points[0].latitude)],
            [Number(points[0].longitude), Number(points[0].latitude)],
        ),
    );

    instance.fitBounds(bounds, { padding: 64, maxZoom: 15, duration: 700, ...options });
}

function flyTo(latitude, longitude, zoom = 14) {
    map.value?.flyTo({ center: [Number(longitude), Number(latitude)], zoom, duration: 900, essential: true });
}

onMounted(async () => {
    const instance = new MapLibreMap({
        container: container.value,
        style: baseStyle(),
        center: [props.config.centre.lng, props.config.centre.lat],
        zoom: props.config.zoom,
        minZoom: props.config.min_zoom,
        maxZoom: props.config.max_zoom,
        attributionControl: { compact: true },
        cooperativeGestures: false,
    });

    map.value = instance;

    instance.addControl(new NavigationControl({ showCompass: false }), 'bottom-right');
    instance.addControl(
        new GeolocateControl({
            positionOptions: { enableHighAccuracy: true },
            trackUserLocation: true,
            showUserLocation: true,
        }),
        'bottom-right',
    );

    instance.on('load', async () => {
        await loadMarkerImages(instance);
        addLayers(instance);
        registerInteractions(instance);
        isLoaded.value = true;
        emit('ready');
        syncClusterMarkers();
    });
});

onBeforeUnmount(() => {
    clusterMarkers.forEach((marker) => marker.remove());
    clusterMarkers.clear();
    map.value?.remove();
    map.value = null;
});

watch(
    () => props.restaurants,
    (restaurants) => {
        const source = map.value?.getSource(SOURCE_ID);

        if (source) {
            source.setData(toFeatureCollection(restaurants));
            syncClusterMarkers();
        }
    },
    { deep: false },
);

watch(
    () => props.selectedId,
    (id) => {
        if (isLoaded.value && map.value?.getLayer(HALO_LAYER)) {
            map.value.setFilter(HALO_LAYER, ['==', ['get', 'id'], id ?? -1]);
        }
    },
);

watch(
    () => props.nightMode,
    () => {
        const instance = map.value;

        if (instance?.getSource('basemap')) {
            instance.getSource('basemap').setTiles([tileUrl()]);
        }
    },
);

defineExpose({ flyTo, fitToRestaurants });
</script>

<template>
    <div class="relative h-full w-full">
        <div ref="container" class="h-full w-full" />

        <div
            v-if="!isLoaded"
            class="pointer-events-none absolute inset-0 flex items-center justify-center paper"
            aria-live="polite"
        >
            <p class="label-caps animate-pulse text-ink/50">Locating the kebabs…</p>
        </div>
    </div>
</template>

<style>
/* Cluster badges are DOM markers, so the map needs no external glyph server. */
.ks-cluster {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    padding: 0;
    border: 2px solid #16130f;
    border-radius: 9999px;
    background: #f5efe1;
    box-shadow: 3px 3px 0 #16130f;
    color: #16130f;
    cursor: pointer;
    transform: scale(var(--ks-cluster-scale, 1));
    transition:
        transform 150ms ease,
        background-color 150ms ease;
}

.ks-cluster:hover {
    background: #d8a426;
    transform: scale(calc(var(--ks-cluster-scale, 1) * 1.08));
}

.ks-cluster[data-best='legendary'] {
    background: #14352a;
    color: #fbf7ee;
}

.ks-cluster[data-best='excellent'] {
    background: #2f6b3a;
    color: #fbf7ee;
}

.ks-cluster__count {
    font-family: 'Bitter', Georgia, serif;
    font-weight: 900;
    font-size: 15px;
    line-height: 1;
}

.ks-cluster__label {
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    opacity: 0.7;
}
</style>
