# api-contentful-integration
A Contentful integration package for garethhudson07/api

## System status monitor

With [oilstone/system-status](https://github.com/oilstone/system-status) installed, the service provider registers a `contentful` monitor type that checks a configured space can be reached with its credentials:

```bash
composer require "oilstone/system-status:^2.0.0-beta.3"
```

| Option | Purpose |
| --- | --- |
| `api` | `delivery` (default), `preview` or `management` |
| `context` | A key of `contentful.environments` to check instead of the default space |
| `name` | Replaces the default name, "Contentful {Api}", or "Contentful {Api} ({context})" with a context |
| `timeout`, `connect_timeout`, `options` | Guzzle settings, as for any monitor using `MakesHttpRequests` (defaults 5 and 3 seconds) |
| `thresholds`, `otherwise` | Response time ratings (default under 1000ms optimal, under 3000ms acceptable) |

The Delivery and Preview APIs are checked by fetching the environment's locales, and the Management API by fetching the environment. Results include the `space` and `environment` metadata, plus the number of `locales` for Delivery and Preview.

A context's token, space and environment are used only when that context has a token for the API being checked. Otherwise the default space's `contentful.{api}.token`, `contentful.space` and `contentful.environment` are, the same rule the package's clients follow. The environment defaults to `master`. An unknown `api` or `context`, or a blank token or space, fails when the monitor is built, naming the config keys to set. A context is checked directly, whether or not `contentfulContext` is set or `contextAware` is on.

The monitor builds its own clients, so its responses are never cached, and the request's locale and `contentful.debug` logging don't apply.

Add it to `system-status.monitors`:

```php
'monitors' => [
    'services' => [
        ['type' => 'contentful'],
        ['type' => 'contentful', 'api' => 'management', 'timeout' => 10],
        ['type' => 'contentful', 'context' => 'europe'],
    ],
],
```

Or use it as a watch's `monitor`:

```php
'watches' => [
    'definitions' => [
        'contentful' => [
            'monitor' => ['type' => 'contentful', 'api' => 'delivery'],
            'failures' => 3,
            'handler' => App\Watches\ServeCachedContent::class,
        ],
    ],
],
```
