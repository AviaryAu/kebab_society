<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Kebab Meter tiers
    |---------------------------------------------------------------------------
    |
    | The Kebab Meter is the primary visual representation of the Kebab Society
    | Score. Copy lives here rather than in Blade/Vue so the Society can revise
    | its verdicts without a code change. Tiers must be ordered ascending and
    | cover 0-100 without gaps.
    |
    | "marker" refers to a sprite in public/images/markers.
    |
    */

    'tiers' => [
        [
            'key' => 'criminal',
            'min' => 0,
            'max' => 39,
            'label' => 'CRIMINAL',
            'verdict' => 'The Society cannot condone this.',
            'marker' => 'questionable',
            'colour' => '#7A1D1D',
        ],
        [
            'key' => 'questionable',
            'min' => 40,
            'max' => 59,
            'label' => 'QUESTIONABLE',
            'verdict' => 'The Society has concerns.',
            'marker' => 'questionable',
            'colour' => '#B3202B',
        ],
        [
            'key' => 'decent',
            'min' => 60,
            'max' => 69,
            'label' => 'DECENT',
            'verdict' => 'Structurally acceptable. Emotionally unremarkable.',
            'marker' => 'average',
            'colour' => '#C2762B',
        ],
        [
            'key' => 'good',
            'min' => 70,
            'max' => 79,
            'label' => 'GOOD',
            'verdict' => 'A reliable kebab. No notes of alarm.',
            'marker' => 'good',
            'colour' => '#A88B2A',
        ],
        [
            'key' => 'excellent',
            'min' => 80,
            'max' => 89,
            'label' => 'EXCELLENT',
            'verdict' => 'Worth crossing a suburb for.',
            'marker' => 'excellent',
            'colour' => '#2F6B3A',
        ],
        [
            'key' => 'legendary',
            'min' => 90,
            'max' => 100,
            'label' => 'LEGENDARY',
            'verdict' => 'The Society has spoken. Go immediately.',
            'marker' => 'legendary',
            'colour' => '#14352A',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Scoring model
    |---------------------------------------------------------------------------
    |
    | The MVP Kebab Society Score is derived from the signals we actually hold
    | today: the Google rating (as a secondary signal only), the volume of
    | opinion behind it, Society reviews once they exist, and an editorial
    | adjustment applied by the Society. Weights must sum to 1.0.
    |
    | The score is deliberately explainable: KebabScoringService returns a
    | breakdown so a restaurant page can justify every point.
    |
    */

    'scoring' => [

        'weights' => [
            'society_rating' => 0.55,
            'google_rating' => 0.30,
            'confidence' => 0.15,
        ],

        // Reviews needed before a rating is treated as fully trustworthy.
        'confidence_review_target' => 150,

        // Star ratings do not use the bottom of their scale in the wild: almost
        // every trading kebab shop sits between 3.0 and 5.0. Anchoring the
        // Kebab Meter at this rating stops everything bunching up at 90+.
        'rating_floor' => 2.5,

        // Ratings are pulled toward this neutral mean until enough reviews
        // exist (Bayesian shrinkage), so a lone 5-star review cannot mint a
        // legendary kebab.
        'prior_rating' => 3.6,
        'prior_weight' => 20,

        // Editorial adjustment bounds, in final score points.
        'editorial_adjustment_limit' => 8,
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
