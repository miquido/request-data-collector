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
        ],
    ],
];
