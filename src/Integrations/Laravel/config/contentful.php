<?php

return [
    /*
     * The ID of the space you want to access.
     */
    'space' => env('CONTENTFUL_SPACE_ID'),

    /*
     * The ID of the environment you want to access.
     */
    'environment' => env('CONTENTFUL_ENVIRONMENT_ID', 'master'),

    /*
     * The default locale to use when querying the API.
     */
    'defaultLocale' => env('CONTENTFUL_DEFAULT_LOCALE', 'en-GB'),

    /*
     * Log Contentful API requests via the application logger. Independent of the application's
     * own debug setting.
     */
    'debug' => (bool) env('CONTENTFUL_DEBUG', false),

    /**
     * PSR-6 cache pool configuration
     */
    'cache' => [
        /*
         * The Laravel cache store backing the PSR-6 pools. Registered by the service provider
         * from the driver and connection below unless the application already defines a store
         * of this name. Leave empty to use the application default store.
         */
        'store' => env('CONTENTFUL_CACHE_STORE', 'contentful'),

        /*
         * Driver used when registering the store.
         */
        'driver' => env('CONTENTFUL_CACHE_DRIVER', 'redis'),

        /*
         * Redis connection used when registering the store.
         */
        'connection' => env('CONTENTFUL_CACHE_CONNECTION', 'contentful'),

        /*
         * Atomic locks are kept off the Contentful connection so they survive a full flush.
         */
        'lockConnection' => env('CONTENTFUL_CACHE_LOCK_CONNECTION', 'default'),

        /*
         * Lifetime of cached entries, assets and content types. Invalidation of the whole pool
         * orphans entries rather than deleting them, so this also bounds how long superseded
         * entries occupy the store.
         */
        'ttl' => (int) env('CONTENTFUL_CACHE_TTL', 86400),

        /*
         * Lifetime of cached query results.
         */
        'queryTtl' => (int) env('CONTENTFUL_QUERY_CACHE_TTL', 300),

        /*
         * Locales to invalidate when removing a single resource from the pool. Derived from the
         * configured default locales when left empty.
         */
        'locales' => array_filter(explode(',', (string) env('CONTENTFUL_CACHE_LOCALES', ''))),
    ],

    /**
     * Delivery/Preview API specific configuration
     */
    'delivery' => [
        /*
         * An API key for the above specified space.
         */
        'token' => env('CONTENTFUL_DELIVERY_TOKEN'),

        /*
         * An array of further client options. See Contentful\Delivery\Client::__construct() for more.
         */
        'delivery.options' => [],
    ],

    /**
     * Preview API specific configuration
     */
    'preview' => [
        /*
         * Serve all requests from the Preview API rather than the Delivery API.
         */
        'enabled' => (bool) env('CONTENTFUL_PREVIEW', false),

        /*
         * An API key for the above specified space.
         */
        'token' => env('CONTENTFUL_PREVIEW_TOKEN'),
    ],

    /**
     * Management API specific configuration
     */
    'management' => [
        /*
         * An API key for the above specified space.
         */
        'token' => env('CONTENTFUL_MANAGEMENT_TOKEN'),
    ],

    /*
     * Allow context aware resources to switch between the spaces/environments defined below.
     * When disabled, the context aware clients resolve to the standard clients for the space
     * and environment configured above.
     */
    'contextAware' => (bool) env('CONTENTFUL_CONTEXT_AWARE', false),

    /*
     * Additional spaces/environments keyed by context. Only used when contextAware is enabled.
     */
    'environments' => [],
];
