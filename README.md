# Requests Data Collector

With this package one can easily start collecting various data that comes through Laravel. It is possible to gain control over excessive database queries and more by analyzing logs.

This package aims to work as a zero-configuration. Although there are default configurations for existing data collectors, take your time to adjust them to own needs. Also, feel free to add own data collectors.

When using this package, every handled request (i.e. not excluded according to `exclusions` rules) will contain `X-Request-Id` header in the response. You can use this ID to correlate various collectors' data.

[![GitHub license](https://img.shields.io/badge/license-Apache2.0-brightgreen.svg)](https://github.com/miquido/request-data-collector/blob/master/LICENSE)
[![Build](https://travis-ci.org/miquido/request-data-collector.svg?branch=master)](https://travis-ci.org/miquido/request-data-collector)

## Set up

### Laravel 5.6+
If You are not using auto-discovery feature, register package's service provider in `config/app.php` file:

```php
	'providers' => [
		// ...
		\Miquido\RequestDataCollector\Providers\LaravelServiceProvider::class,
	],
```

### Lumen

Register package's service provider in `bootstrap/app.php` file:

```php
$app->register(\Miquido\RequestDataCollector\Providers\LumenServiceProvider::class);
``` 

If You want to override default configuration, don't forget to copy the default one to `/config/request-data-collector.php` file and load it in `bootstrap/app.php` file:

```php
$app->configure('request-data-collector');
```

### Further configuration

Add new environment variable (e.g. to `.env` file):

```ini
REQUESTS_DATA_COLLECTOR_ENABLED=true
```

That's it! By default, only basic information about requests are collected and pushed to logs (according to `LOG_CHANNEL` channel).

### Publishing default configuration

```bash
php artisan vendor:publish --provider="Miquido\RequestDataCollector\Providers\LaravelServiceProvider"
```

## Available Data Collectors

There are some predefined Data Collectors that might be used.

### RequestResponseCollector

This collector is used to collect data about the incoming request and ongoing response.

```php
'request' => [
	'driver' => \Miquido\RequestDataCollector\Collectors\RequestResponseCollector::class,

	'request_info' => [
		// ...
	],

	'response_info' => [
		// ...
	],

	'variables' => [
		// ...
	],

	'raw' => boolean,
],
```

#### request_info

Defines a list of request parameters that should be collected. For list of available options, see `\Miquido\RequestDataCollector\Collectors\RequestResponseCollector::REQUEST_INFO_*` constants.

Example:

```php
'request_info' => [
	\Miquido\RequestDataCollector\Collectors\RequestResponseCollector::REQUEST_INFO_REAL_METHOD,
	\Miquido\RequestDataCollector\Collectors\RequestResponseCollector::REQUEST_INFO_PATH_INFO,
],
```

#### response_info

Defines a list of response parameters that should be collected. For list of available options, see `\Miquido\RequestDataCollector\Collectors\RequestResponseCollector::RESPONSE_INFO_*` constants.

Example:

```php
'response_info' => [
	\Miquido\RequestDataCollector\Collectors\RequestResponseCollector::RESPONSE_INFO_HTTP_STATUS_CODE,
],
```

#### variables

Defines a list of variables associated with request that should be collected. For list of available options, see `\Miquido\RequestDataCollector\Collectors\RequestResponseCollector::VARIABLE_*` constants.

It is possible to collect all information about variable:

```php
'variables' => [
	\Miquido\RequestDataCollector\Collectors\RequestResponseCollector::VARIABLE_GET,
],
```

Or include/exclude some of them (which is especially useful when dealing with sensitive data):

```php
// Incoming request: /some/page?email=...&password=...

'variables' => [
	\Miquido\RequestDataCollector\Collectors\RequestResponseCollector::VARIABLE_GET => [
		'excludes' => [
			'password',
		],
		
		'includes' => [
			'email',
		],
	],
],
```

It is worth mentioning, that **inclusions have priority over exclusions**. This means if inclusions are used, exclusions are not applied at all.

#### raw

When set to `true`, request will be remembered as soon as reaches application (allows to have unmodified request).
When set to `false`, data collection will use request at the end of application lifetime (allows to include request modifications).

Please note that raw request does not have routing information, so `\Miquido\RequestDataCollector\Collectors\RequestResponseCollector::REQUEST_INFO_ROUTE` option will not have effect.

### DatabaseQueriesCollector

This collector is used to collect data about performed database queries.

Please note, that it uses Laravel's built-in `\DB::enable/disable/flushQueryLog()` methods, so if it is also used somewhere else in the code, it might have impact on the final result.

```php
'database' => [
	'driver' => \Miquido\RequestDataCollector\Collectors\DatabaseQueriesCollector::class,

	'connections' => [
		// ...
	],
],
```

#### connections

Defines a list of connections to databases from which queries should be collected. Use names defined in `config/database.php` file. It is also possible to provide `null` value as a name to collect queries from default connection.

## Enabling or disabling Data Collectors

Each collector's configuration consists of two parts: entry in `collectors` array and entry in `options` array.

The `collectors` array contains key-value entries, where key is collector name and value is either `true` or `false`, depending on if it should be enabled or disabled. Suggestion: if it is required to dynamically enable/disable specified collectors, one might want to define different environmental variables for each collector itself (e.g. `DATABASE_COLLECTOR_ENABLED` etc.).

The `options` array contains key-value entries, where key is collector name and value is an array with its configuration. It is required for it to have at least `driver` setting. Every other settings are collector dependent.

The `key` with collector name will be used for logging purposes. Every log entry contains information about request ID and given collector name to easier find/filter it.

## Excluding requests from collecting

It is possible to exclude some requests that should not be collected at all.

```php
'exclude' => [
	[
		'filter' => class reference,
		'with'   => [
			// ...
		],
	],
	
	// ...
],
```

Each entry consists of:

**filter** containing filter class reference (e.g. `\Miquido\RequestDataCollector\Filters\UserAgentFilter::class`).

**with** containing data provided for filter class constructor (e.g. `'userAgents' => ['Docker HEALTHCHECK'], ...`). Note: Laravel's container is used here so it is possible to make use of Dependency Injection.

Feel fre to use one of available filters (see `src/Filters` directory) or write your own.

## Tracking the request through many services

You can track request through many services. When `Request ID` is being generated, it is firstly checked if there is `X-REQUEST-ID` header present in the request, and its value is being used instead. This way You can see same `Request ID` in logs.

When there is no `X-REQUEST-ID` header available, You can still set Your custom `Request ID` via `\Miquido\RequestDataCollector\RequestDataCollector::setRequestId()` method.

In both cases `Request ID` has to be in following format:

`X[0-9a-fA-F]{32}`

If `X-REQUEST-ID` has invalid format it will be silently skipped and new ID will be generated.
