# ShopSavvy Data API - PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/shopsavvy/shopsavvy-sdk-php.svg)](https://packagist.org/packages/shopsavvy/shopsavvy-sdk-php)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.1-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Official PHP SDK for the [ShopSavvy Data API](https://shopsavvy.com/data): product details, current offers across retailers, price history, deals, and scheduled price monitoring. Works in any PHP 8.1+ application, including Laravel, Symfony and WordPress.

## Installation

```bash
composer require shopsavvy/shopsavvy-sdk-php
```

Requires PHP 8.1+ and `ext-json`. Guzzle 7 is installed automatically.

Get an API key at [shopsavvy.com/data](https://shopsavvy.com/data).

## Quick start

```php
<?php
require 'vendor/autoload.php';

use ShopSavvy\SDK\ShopSavvyClient;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;

$client = new ShopSavvyClient(getenv('SHOPSAVVY_API_KEY'));

try {
    $response = $client->getCurrentOffers('611247373064');

    foreach ($response->data as $product) {
        echo $product['title'] . "\n";

        $offers = $product['offers'];
        usort($offers, fn ($a, $b) => $a['price'] <=> $b['price']);

        foreach ($offers as $offer) {
            echo "  {$offer['retailer']}: {$offer['price']} ({$offer['condition']})\n";
        }
    }
} catch (ShopSavvyException $e) {
    echo 'ShopSavvy error: ' . $e->getMessage() . "\n";
}
```

## Client

```php
$client = new ShopSavvyClient(
    'ss_live_...',                    // API key (ss_live_ or ss_test_ followed by 32 characters)
    'https://api.shopsavvy.com/v1',   // optional base URL
    30.0,                             // optional timeout in seconds
    ['proxy' => 'http://proxy:8080']  // optional extra Guzzle client options
);
```

## Identifiers

Every product method accepts any of: UPC / EAN / ISBN / GTIN barcode, Amazon ASIN, model number, a product URL at any retailer, a product name, or a ShopSavvy product ID.

## Methods

Most methods return `ShopSavvy\SDK\Models\ApiResponse`. Its `data` property holds the decoded JSON `data` field exactly as the API sends it (arrays), and `meta` holds the response metadata.

### Search

```php
$result = $client->searchProducts('keurig k-mini', limit: 10);

foreach ($result->data as $product) {          // ShopSavvy\SDK\Models\ProductDetails
    echo "{$product->title} ({$product->brand}) barcode={$product->barcode}\n";
}

echo $result->pagination?->total;              // total matches
```

### Product details

```php
$response = $client->getProductDetails('B07G14HTBZ');
$product = $response->data[0];                 // ['title' => ..., 'brand' => ..., 'barcode' => ..., 'images' => [...], ...]

$many = $client->getProductDetailsBatch(['611247373064', 'B0788F3R8X']);
```

### Current offers

```php
$response = $client->getCurrentOffers('611247373064');
$sameAtOneRetailer = $client->getCurrentOffers('611247373064', 'amazon.com');   // retailer domain
$several = $client->getCurrentOffersBatch(['611247373064', 'B0788F3R8X']);

// Each product in $response->data carries an 'offers' list:
// ['id', 'retailer', 'price', 'availability' ('in' | 'out'), 'condition' ('new' | 'used' | 'refurbished'), 'seller', 'URL', 'timestamp']
```

### CSV

`getProductDetails`, `getProductDetailsBatch`, `getCurrentOffers`, `getCurrentOffersBatch` and
`getPriceHistory` accept a `$format` of `'csv'`. The API then answers `text/csv`, and these methods
return the raw CSV text as a `string` instead of a response object:

```php
$csv = $client->getCurrentOffers('611247373064', null, 'csv');   // string
file_put_contents('offers.csv', $csv);
```

### Price history

```php
$response = $client->getPriceHistory('611247373064', '2026-01-01', '2026-01-31');
$amazonOnly = $client->getPriceHistory('611247373064', '2026-01-01', '2026-01-31', 'amazon.com');

// $response is a PriceHistoryResponse. `data` holds one ProductWithPriceHistory per product
// found (all the product fields, plus `offers`); each OfferWithHistory carries a `history`
// list of PriceHistoryEntry points, newest first.
foreach ($response->data as $product) {
    echo "{$product->title} ({$product->barcode})\n";
    foreach ($product->offers as $offer) {
        foreach ($offer->history as $point) {
            // $point->currency is null on the rare archived point with no recorded currency;
            // $point->availability is null when it was unknown.
            echo "  {$offer->retailer} {$point->timestamp}: {$point->price} {$point->currency}\n";
        }
    }
}

echo "Credits used: {$response->creditsUsed()}\n";
```

### Scheduled monitoring

```php
// PUT /products/scheduled — 'hourly', 'daily' or 'weekly'; a comma-separated list schedules several
$result = $client->scheduleProductMonitoring('611247373064', 'daily');
$amazonOnly = $client->scheduleProductMonitoring('611247373064,B08N5WRWNW', 'hourly', 'amazon.com');

// $result is a ScheduleResponse; `data` holds one ScheduledProduct per product found.
// A ScheduledProduct is a ProductDetails (title, shopsavvy, barcode, ...) plus `schedule`
// and `retailer` (set only when the schedule is limited to one retailer).
foreach ($result->data as $product) {
    echo "{$product->title} ({$product->shopsavvy}): {$product->schedule}\n";
}
echo "Credits used: {$result->creditsUsed()}\n";

// GET /products/scheduled — a ScheduledProductsResponse with every scheduled product.
// `schedule` is null for an interval with no Data API label; `retailer` is null when the
// product is watched at every retailer.
foreach ($client->getScheduledProducts()->data as $product) {
    $where = $product->retailer ?? 'all retailers';
    echo "{$product->title}: " . ($product->schedule ?? 'custom interval') . " at {$where}\n";
}

// DELETE /products/scheduled — a RemoveResponse with `success`, `message` and `meta` (no data)
$removed = $client->removeProductFromSchedule('611247373064');
if ($removed->success) {
    echo $removed->message . "\n";   // "Products successfully removed from schedule"
}
```

### Deals

```php
$deals = $client->getDeals(['sort' => 'hot', 'limit' => 10, 'grade' => 'B']);

foreach ($deals['deals'] as $deal) {
    echo "{$deal['title']} - {$deal['grade']['letter']}{$deal['grade']['suffix']}\n";
}
```

`sort` is one of `hot`, `new`, `top-hour`, `top-day`, `top-week`. Other filters: `category`, `retailer`, `tag`, `min_price`, `max_price`, `offset`.

### Reviews, batch lookup, webhooks, usage

```php
$review = $client->getProductReview('B09XS7JWHH');                     // TLDR review: pros, cons, verdict, scores

$batch = $client->batchLookup(['611247373064', 'B0788F3R8X'], ['offers']);
// batches over 20 identifiers run asynchronously: poll with $client->getBatchStatus($batchId)

$webhook = $client->createWebhook('https://example.com/hooks/shopsavvy', ['price_drop']);
$client->listWebhooks();
$client->updateWebhook($webhookId, isActive: false);
$client->testWebhook($webhookId);
$client->deleteWebhook($webhookId);

$usage = $client->getUsage();
```

## Errors

Every failure throws a `ShopSavvy\SDK\Exceptions\ShopSavvyException` (the HTTP status is the exception code). Subclasses let you handle specific cases:

| Exception | When |
|---|---|
| `ShopSavvyAuthenticationException` | 401, missing or invalid API key |
| `ShopSavvyValidationException` | 400 / 422, invalid parameters |
| `ShopSavvyNotFoundException` | 404, e.g. no product matched the identifier |
| `ShopSavvyRateLimitException` | 429, too many requests |
| `ShopSavvyNetworkException` | the request never got a response |

```php
use ShopSavvy\SDK\Exceptions\{ShopSavvyException, ShopSavvyNotFoundException, ShopSavvyRateLimitException};

try {
    $client->getProductDetails('012345678901');
} catch (ShopSavvyNotFoundException $e) {
    // no product for that identifier
} catch (ShopSavvyRateLimitException $e) {
    sleep(5);
} catch (ShopSavvyException $e) {
    error_log($e->getMessage());
}
```

## Framework integration

- **Laravel**: [`shopsavvy/laravel-shopsavvy`](https://github.com/shopsavvy/laravel-shopsavvy) adds a facade, config file, Blade components and Artisan commands (it is a standalone package with its own client built on Laravel's HTTP client).
- **Symfony**: register `ShopSavvy\SDK\ShopSavvyClient` as a service with `$apiKey: '%env(SHOPSAVVY_API_KEY)%'`.
- **WordPress**: install with Composer in your plugin and store the API key in an option.

## Development

```bash
git clone https://github.com/shopsavvy/sdk-php.git
cd sdk-php
composer install
composer test       # PHPUnit
composer phpstan    # static analysis, level 8
```

## Links

- [Data API documentation](https://shopsavvy.com/data/documentation)
- [Integration page](https://shopsavvy.com/integrations/sdk-php)
- [Packagist](https://packagist.org/packages/shopsavvy/shopsavvy-sdk-php)
- [Issues](https://github.com/shopsavvy/sdk-php/issues)

## License

MIT. See [LICENSE](LICENSE).

Made by [Monolith Technologies, Inc.](https://shopsavvy.com)
