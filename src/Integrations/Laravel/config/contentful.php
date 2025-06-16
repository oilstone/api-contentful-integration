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

    'environments' => [],
];
