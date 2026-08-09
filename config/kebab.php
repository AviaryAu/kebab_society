<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Kebab Meter tiers
    |---------------------------------------------------------------------------
    |
    | The Society publishes a five star rating, on the same scale people already
    | read on Google. Tiers must be ordered ascending and cover 0.0 - 5.0
    | without gaps. Copy lives here rather than in Vue so the Society can revise
    | its verdicts without a code change.
    |
    | "marker" refers to a sprite in public/images/markers, whose illustrated
    | star badge matches the band.
    |
    */

    'tiers' => [
        [
            'key' => 'questionable',
            'min' => 0.0,
            'max' => 2.99,
            'stars' => 1,
            'label' => 'QUESTIONABLE',
            'verdict' => 'The Society has concerns.',
            'marker' => 'questionable',
            'colour' => '#B3202B',
        ],
        [
            'key' => 'average',
            'min' => 3.0,
            'max' => 3.49,
            'stars' => 2,
            'label' => 'ACCEPTABLE',
            'verdict' => 'Structurally sound. Emotionally unremarkable.',
            'marker' => 'average',
            'colour' => '#C2762B',
        ],
        [
            'key' => 'good',
            'min' => 3.5,
            'max' => 3.99,
            'stars' => 3,
            'label' => 'GOOD',
            'verdict' => 'A reliable kebab. No notes of alarm.',
            'marker' => 'good',
            'colour' => '#A88B2A',
        ],
        [
            'key' => 'excellent',
            'min' => 4.0,
            'max' => 4.49,
            'stars' => 4,
            'label' => 'EXCELLENT',
            'verdict' => 'Worth crossing a suburb for.',
            'marker' => 'excellent',
            'colour' => '#2F6B3A',
        ],
        [
            'key' => 'legendary',
            'min' => 4.5,
            'max' => 5.0,
            'stars' => 5,
            'label' => 'LEGENDARY',
            'verdict' => 'The Society has spoken. Go immediately.',
            'marker' => 'legendary',
            'colour' => '#14352A',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Unrated
    |---------------------------------------------------------------------------
    |
    | A restaurant on the register that the Society cannot yet rate. It is shown
    | honestly rather than given a flattering default.
    |
    */

    'unrated_tier' => [
        'key' => 'unrated',
        'min' => 0.0,
        'max' => 0.0,
        'stars' => 0,
        'label' => 'UNRATED',
        'verdict' => 'On the register. Awaiting a verdict.',
        'marker' => 'unrated',
        'colour' => '#7A756C',
    ],

    /*
    |---------------------------------------------------------------------------
    | Rating model
    |---------------------------------------------------------------------------
    |
    | The Kebab Society Rating is published out of five. Today it is derived
    | from the Google rating, pulled toward a neutral prior in proportion to how
    | few reviews stand behind it, plus a small, bounded, disclosed editorial
    | adjustment. Society member reviews take the majority of the weight as soon
    | as they exist.
    |
    | Weights are normalised across whichever signals are actually held.
    |
    */

    'rating' => [

        'weights' => [
            'society_rating' => 0.60,
            'google_rating' => 0.40,
        ],

        // Reviews needed before a rating is treated as fully trustworthy.
        'confidence_review_target' => 150,

        // Ratings are pulled toward this neutral mean until enough reviews
        // exist (Bayesian shrinkage), so a lone five star review cannot mint a
        // legendary kebab.
        'prior_rating' => 3.6,
        'prior_weight' => 20,

        // Editorial adjustment bounds, in stars.
        'editorial_adjustment_limit' => 0.3,
    ],

    /*
    |---------------------------------------------------------------------------
    | Photographs
    |---------------------------------------------------------------------------
    |
    | Uploads are stored on Laravel Cloud's object storage in production (the
    | "s3" disk, configured entirely through environment variables) and on the
    | public disk locally. Each upload is resized into the formats below; the
    | original upload is never served to visitors.
    |
    */

    'photos' => [
        'disk' => env('KEBAB_PHOTO_DISK', env('FILESYSTEM_DISK', 'public')),
        'directory' => 'restaurant-photos',
        'max_upload_kilobytes' => 12288, // 12MB
        'quality' => 82,

        'formats' => [
            'thumb' => ['width' => 400, 'height' => 300],
            'card' => ['width' => 900, 'height' => 600],
            'hero' => ['width' => 1800, 'height' => 1200],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Late night
    |---------------------------------------------------------------------------
    |
    | A kebab shop counts as "late night" if it is still trading at this hour.
    | Expressed in the application timezone.
    |
    */

    'late_night_hour' => 0, // midnight

    /*
    |---------------------------------------------------------------------------
    | Map defaults
    |---------------------------------------------------------------------------
    */

    'map' => [
        'centre' => ['lat' => -33.8688, 'lng' => 151.2093],
        'zoom' => 11,
        'min_zoom' => 9,
        'max_zoom' => 18,

        // Free, key-less raster basemap (CARTO). Attribution is rendered by the
        // map component and must not be removed.
        'tiles' => [
            'day' => 'https://basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            'night' => 'https://basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}{r}.png',
        ],
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions" target="_blank" rel="noopener">CARTO</a>',
    ],

];
