<?php
declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable Request Data Collector.
    |
    */
    'enabled' => env('REQUESTS_DATA_COLLECTOR_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    |
    | Enable or disable request tracking. When enabled, before a Request ID is
    | generated, it is first checked if request has X-REQUEST-ID header and if
    | so, and value is valid Request ID format, then it is used instead of the
    | generated one.
    |
    */
    'tracking' => env('REQUESTS_DATA_COLLECTOR_ALLOW_TRACKING', true),

    /*
    |--------------------------------------------------------------------------
    | Exclude request
    |--------------------------------------------------------------------------
    |
    | Exclude requests matching to listed filters.
    |
    */
    'exclude' => [
        [
            'filter' => \Miquido\RequestDataCollector\Filters\UserAgentFilter::class,
            'with'   => [
                'userAgents' => [
                    'Docker HEALTHCHECK',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging format
    |--------------------------------------------------------------------------
    |
    | Determines whether collected statistics should be logged in single log
    | entry or separately. This is global setting. It will be applied only to
    | collectors supporting this feature and can be individually overridden.
    |
    | Available values (also as RequestDataCollector::LOGGING_FORMAT_*):
    | - single   - single log entry is produced.
    | - separate - collected statistics are logged in separate entries.
    |
    */
    'logging_format' => env('REQUESTS_DATA_COLLECTOR_LOGGING_FORMAT', \Miquido\RequestDataCollector\RequestDataCollector::LOGGING_FORMAT_SINGLE),

    /*
    |--------------------------------------------------------------------------
    | Channel
    |--------------------------------------------------------------------------
    |
    | Choose channel that logs should be push to.
    |
    */
    'channel' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Collectors
    |--------------------------------------------------------------------------
    |
    | List of collectors.
    |
    */
    'collectors' => [
        'request'  => true,
        'database' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Collectors' options.
    |--------------------------------------------------------------------------
    |
    | Provide options for collectors. Define collector options as follows:
    |  'collector_name' => [
    |   'driver'    => Collector class (e.g. RequestResponseCollector::class),
    |   'option1'   => value,
    |   'option2'   => value,
    |    ...
    |   'other_options'   => value,
    |  ],
    |
    */
    'options' => [
        'request' => [
            'driver' => \Miquido\RequestDataCollector\Collectors\RequestResponseCollector::class,

            /*
             * RequestResponseCollector will collect only following information about the request.
             *
             * For list of available options, see RequestResponseCollector::REQUEST_INFO_* constants.
             */
            'request_info' => [
                \Miquido\RequestDataCollector\Collectors\RequestResponseCollector::REQUEST_INFO_REAL_METHOD,
                \Miquido\RequestDataCollector\Collectors\RequestResponseCollector::REQUEST_INFO_PATH_INFO,
            ],

            /*
             * RequestResponseCollector will collect only following information about the response.
             *
             * For list of available options, see RequestResponseCollector::RESPONSE_INFO_* constants.
             */
            'response_info' => [
                \Miquido\RequestDataCollector\Collectors\RequestResponseCollector::RESPONSE_INFO_HTTP_STATUS_CODE,
                \Miquido\RequestDataCollector\Collectors\RequestResponseCollector::RESPONSE_INFO_CONTENT,
            ],

            /*
             * RequestResponseCollector will collect only following request variables.
             *
             * For list of available options, see RequestResponseCollector::VARIABLE_* constants.
             *
             * You can simply collect full arrays (e.g. _GET) by adding it's name to variables array.
             *   \Miquido\RequestDataCollector\Collectors\RequestResponseCollector::VARIABLE_GET,
             *
             * For filter purposes, you can use includes or excludes array. Both, includes and excludes fields are optional.
             * Empty include array will cause no data collecting.
             * When includes array is provided, excludes filter is not used.
             *  \Miquido\RequestDataCollector\Collectors\RequestResponseCollector::VARIABLE_SERVER => [
             *      'excludes' => [],
             *      'includes' => ['HTTP_USER_AGENT'],
             *  ],
             */
            'variables' => [
                //
            ],

            /*
             * When set to true, request will be remembered as soon as reaches application (allows to have unmodified request).
             * When set to false, data collection will use request at the end of application lifetime (allows to include request modifications).
             */
            'raw' => true,
        ],

        'database' => [
            'driver' => \Miquido\RequestDataCollector\Collectors\DatabaseQueriesCollector::class,

            'connections' => [
                null,   // Default connection
            ],

            /*
             * You can override global logging format here.
             * If null, global logging format is used.
             */
            'logging_format' => null,
        ],
    ],
];
