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
 * THE KEEP SYDNEY LIVE MAP
 *
 * An editorial city map, not a product map. Points are drawn by a symbol layer
 * using KS markers generated on a canvas at runtime, so the identity stays in
 * code rather than in binary sprite art. At low zoom the points collapse into
 * clusters, rendered as DOM markers so the map needs no external glyph server.
 */

const props = defineProps({
    items: { type: Array, default: () => [] },
    config: { type: Object, required: true },
    selectedId: { type: [Number, String], default: null },
    nightMode: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'ready']);

const SOURCE_ID = 'ks-places';
const CLUSTER_HIT_LAYER = 'ks-cluster-hitbox';
const POINT_LAYER = 'ks-points';
const HALO_LAYER = 'ks-selected-halo';

const INK = '#171717';

/**
 * Markers carry the pastel library. Categories are grouped rather than each
 * owning a permanent colour, so the map stays editorial instead of turning
 * into a rainbow key.
 */
const MARKER_PALETTE = {
    music: '#BFD8E5',
    nightlife: '#D8CBE5',
    comedy: '#F2DF9B',
    theatre: '#F2C8C3',
    festivals: '#F2C8C3',
    arts: '#D8CBE5',
    sport: '#C7D1BC',
    'food-drink': '#E9C4A8',
    venue: '#FCFBF8',
    default: '#C8DED5',
};

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

function markerKey(item) {
    if (item.type === 'venue') {
        return 'venue';
    }

    return MARKER_PALETTE[item.category_slug] ? item.category_slug : 'default';
}

function toFeatureCollection(items) {
    return {
        type: 'FeatureCollection',
        features: items
            .filter((item) => Number.isFinite(Number(item.longitude)) && Number.isFinite(Number(item.latitude)))
            .map((item) => ({
                type: 'Feature',
                id: item.id,
                geometry: {
                    type: 'Point',
                    coordinates: [Number(item.longitude), Number(item.latitude)],
                },
                properties: {
                    id: item.id,
                    name: item.name,
                    marker: `ks-marker-${markerKey(item)}`,
                },
            })),
    };
}

/**
 * Draws the `[ KS ]` marker: a small squared badge with a hairline ink rule and
 * a short stem, which stays readable down to about 20px.
 */
function drawMarker(fill) {
    const scale = 2;
    const width = 44 * scale;
    const height = 52 * scale;
    const boxHeight = 34 * scale;

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    context.scale(scale, scale);
    context.lineWidth = 1.5;
    context.strokeStyle = INK;
    context.fillStyle = fill;

    context.beginPath();
    context.rect(2, 2, 40, boxHeight / scale);
    context.fill();
    context.stroke();

    // Stem, so the badge points at the place rather than floating over it.
    context.beginPath();
    context.moveTo(22, 2 + boxHeight / scale);
    context.lineTo(22, 48);
    context.stroke();

    context.fillStyle = INK;
    context.font = `600 ${19}px Anton, 'Arial Narrow', sans-serif`;
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText('KS', 22, 3 + boxHeight / scale / 2);

    return context.getImageData(0, 0, width, height);
}

function loadMarkerImages(instance) {
    Object.entries(MARKER_PALETTE).forEach(([key, fill]) => {
        const id = `ks-marker-${key}`;

        if (!instance.hasImage(id)) {
            instance.addImage(id, drawMarker(fill), { pixelRatio: 2 });
        }
    });
}

function addLayers(instance) {
    instance.addSource(SOURCE_ID, {
        type: 'geojson',
        data: toFeatureCollection(props.items),
        cluster: true,
        clusterRadius: 52,
        clusterMaxZoom: 13,
    });

    // Invisible circles give the DOM cluster badges something to be queried from.
    instance.addLayer({
        id: CLUSTER_HIT_LAYER,
        type: 'circle',
        source: SOURCE_ID,
        filter: ['has', 'point_count'],
        paint: { 'circle-radius': 1, 'circle-opacity': 0 },
    });

    instance.addLayer({
        id: HALO_LAYER,
        type: 'circle',
        source: SOURCE_ID,
        filter: ['==', ['get', 'id'], -1],
        paint: {
            'circle-radius': 26,
            'circle-color': INK,
            'circle-opacity': 0.08,
            'circle-stroke-width': 1,
            'circle-stroke-color': INK,
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
            'icon-size': ['interpolate', ['linear'], ['zoom'], 9, 0.45, 13, 0.65, 16, 0.85],
        },
    });
}

function clusterElement(count) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'ks-cluster';
    element.setAttribute('aria-label', `${count} places. Zoom in.`);
    element.textContent = String(count);

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

        const element = clusterElement(cluster.properties.point_count);

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

function findItem(id) {
    return props.items.find((item) => String(item.id) === String(id)) ?? null;
}

function registerInteractions(instance) {
    instance.on('click', POINT_LAYER, (event) => {
        const feature = event.features?.[0];
        const item = feature ? findItem(feature.properties.id) : null;

        if (item) {
            emit('select', item);
        }
    });

    instance.on('mouseenter', POINT_LAYER, () => {
        instance.getCanvas().style.cursor = 'pointer';
    });

    instance.on('mouseleave', POINT_LAYER, () => {
        instance.getCanvas().style.cursor = '';
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

function fitToItems(items = props.items, options = {}) {
    const instance = map.value;
    const points = items.filter(
        (item) => Number.isFinite(Number(item.latitude)) && Number.isFinite(Number(item.longitude)),
    );

    if (!instance || points.length === 0) {
        return;
    }

    if (points.length === 1) {
        instance.easeTo({ center: [Number(points[0].longitude), Number(points[0].latitude)], zoom: 15, duration: 700 });

        return;
    }

    const bounds = points.reduce(
        (accumulator, item) => accumulator.extend([Number(item.longitude), Number(item.latitude)]),
        new LngLatBounds(
            [Number(points[0].longitude), Number(points[0].latitude)],
            [Number(points[0].longitude), Number(points[0].latitude)],
        ),
    );

    instance.fitBounds(bounds, { padding: 72, maxZoom: 15, duration: 700, ...options });
}

function flyTo(latitude, longitude, zoom = 14) {
    map.value?.flyTo({ center: [Number(longitude), Number(latitude)], zoom, duration: 900, essential: true });
}

onMounted(async () => {
    // The marker art is drawn with Anton; wait for it so the badges are not
    // rasterised in a fallback face.
    await document.fonts?.ready;

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

    instance.on('load', () => {
        loadMarkerImages(instance);
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
    () => props.items,
    (items) => {
        const source = map.value?.getSource(SOURCE_ID);

        if (source) {
            source.setData(toFeatureCollection(items));
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

defineExpose({ flyTo, fitToItems });
</script>

<template>
    <div class="relative h-full w-full">
        <div ref="container" class="h-full w-full" />

        <div
            v-if="!isLoaded"
            class="pointer-events-none absolute inset-0 flex items-center justify-center bg-paper"
            aria-live="polite"
        >
            <p class="label-caps text-charcoal">Drawing Sydney</p>
        </div>
    </div>
</template>

<style>
/* Cluster badges are DOM markers, so the map needs no external glyph server. */
.ks-cluster {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    padding: 0;
    border: 1px solid #171717;
    border-radius: 9999px;
    background: #fcfbf8;
    color: #171717;
    font-family: 'Oswald', 'Arial Narrow', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition:
        background-color 200ms ease,
        color 200ms ease;
}

.ks-cluster:hover {
    background: #171717;
    color: #fcfbf8;
}
</style>
