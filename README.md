# ShopSavvy Data API - PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/shopsavvy/shopsavvy-sdk-php.svg)](https://packagist.org/packages/shopsavvy/shopsavvy-sdk-php)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.0-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Documentation](https://img.shields.io/badge/docs-shopsavvy.com-blue)](https://shopsavvy.com/data/documentation)

Official PHP SDK for the [ShopSavvy Data API](https://shopsavvy.com/data). Access comprehensive product data, real-time pricing, and historical price trends across **thousands of retailers** and **millions of products**. Built for **Laravel**, **Symfony**, **WordPress**, and any PHP 8.0+ application.

## ⚡ 30-Second Quick Start

```php
// Install: composer require shopsavvy/shopsavvy-sdk-php

<?php
require_once 'vendor/autoload.php';

use ShopSavvy\SDK\ShopSavvyClient;

$client = new ShopSavvyClient('ss_live_your_api_key_here');

$product = $client->getProductDetails('012345678901');
$offers = $client->getCurrentOffers('012345678901');
$bestOffer = collect($offers->data)->sortBy('price')->first();

echo "{$product->data->name} - Best price: \${$bestOffer->price} at {$bestOffer->retailer}";
```

## 🚀 Installation & Setup

### Requirements

- PHP 8.0 or higher
- Composer 2.0+
- ext-json (typically included)
- Guzzle HTTP 7.0+ (auto-installed)

### Composer Installation

```bash
composer require shopsavvy/shopsavvy-sdk-php
```

### Framework-Specific Setup

#### Laravel
```bash
# Optional: Publish config (creates config/shopsavvy.php)
php artisan vendor:publish --provider="ShopSavvy\SDK\Laravel\ShopSavvyServiceProvider"

# Add to .env
SHOPSAVVY_API_KEY=ss_live_your_api_key_here
```

#### Symfony
```yaml
# config/packages/shopsavvy.yaml
shopsavvy:
    api_key: '%env(SHOPSAVVY_API_KEY)%'
    timeout: 60
```

### Get Your API Key

1. **Sign up**: Visit [shopsavvy.com/data](https://shopsavvy.com/data)
2. **Choose plan**: Select based on your usage needs  
3. **Get API key**: Copy from your dashboard
4. **Test**: Run the 30-second example above

## 📖 Complete API Reference

### Client Configuration

```php
<?php
require_once 'vendor/autoload.php';

use ShopSavvy\SDK\ShopSavvyClient;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;

// Basic configuration
$client = new ShopSavvyClient('ss_live_your_api_key_here');

// Advanced configuration
$client = new ShopSavvyClient(
    'ss_live_your_api_key_here',
    'https://api.shopsavvy.com/v1',  // Custom base URL
    60.0,                            // Request timeout
    3,                               // Retry attempts
    'MyApp/1.0.0'                   // Custom user agent
);

// Environment variable configuration
$apiKey = $_ENV['SHOPSAVVY_API_KEY'] ?? getenv('SHOPSAVVY_API_KEY');
$client = new ShopSavvyClient($apiKey);
```

### Product Lookup

#### Single Product
```php
// Look up by barcode, ASIN, URL, model number, or ShopSavvy ID
$product = $client->getProductDetails('012345678901');
$amazonProduct = $client->getProductDetails('B08N5WRWNW');
$urlProduct = $client->getProductDetails('https://www.amazon.com/dp/B08N5WRWNW');
$modelProduct = $client->getProductDetails('MQ023LL/A'); // iPhone model number

echo "📦 Product: {$product->data->name}\n";
echo "🏷️ Brand: " . ($product->data->brand ?? 'N/A') . "\n";
echo "📂 Category: " . ($product->data->category ?? 'N/A') . "\n";
echo "🔢 Product ID: {$product->data->id}\n";

if ($product->data->asin) {
    echo "📦 ASIN: {$product->data->asin}\n";
}

if ($product->data->modelNumber) {
    echo "🔧 Model: {$product->data->modelNumber}\n";
}
```

#### Bulk Product Lookup
```php
// Process up to 100 products at once (Pro plan)
$identifiers = [
    '012345678901', 'B08N5WRWNW', '045496590048',
    'https://www.bestbuy.com/site/product/123456',
    'MQ023LL/A', 'SM-S911U'  // iPhone and Samsung model numbers
];

$products = $client->getProductDetailsBatch($identifiers);

foreach ($products->data as $index => $product) {
    if ($product !== null) {
        echo "✓ Found: {$product->name} by " . ($product->brand ?? 'Unknown') . "\n";
    } else {
        echo "❌ Failed to find product: {$identifiers[$index]}\n";
    }
}
```

### Real-Time Pricing

#### Laravel Price Comparison Component
```php
<?php
// app/Http/Controllers/PriceComparisonController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use ShopSavvy\SDK\ShopSavvyClient;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PriceComparisonController extends Controller
{
    private ShopSavvyClient $client;
    
    public function __construct()
    {
        $this->client = new ShopSavvyClient(config('shopsavvy.api_key'));
    }
    
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string'
        ]);
        
        try {
            $product = $this->client->getProductDetails($request->identifier);
            $offers = $this->client->getCurrentOffers($request->identifier);
            
            // Sort offers by price
            $sortedOffers = collect($offers->data)
                ->filter(fn($offer) => $offer->price !== null)
                ->sortBy('price')
                ->values();
            
            $bestOffer = $sortedOffers->first();
            $worstOffer = $sortedOffers->last();
            $averagePrice = $sortedOffers->avg('price');
            
            // Calculate savings
            $potentialSavings = $worstOffer ? ($worstOffer->price - $bestOffer->price) : 0;
            
            // Filter by availability and condition
            $inStockOffers = $sortedOffers->where('availability', 'in_stock');
            $newConditionOffers = $sortedOffers->where('condition', 'new');
            
            return response()->json([
                'success' => true,
                'product' => [
                    'name' => $product->data->name,
                    'brand' => $product->data->brand,
                    'category' => $product->data->category,
                    'id' => $product->data->id
                ],
                'pricing' => [
                    'best_price' => $bestOffer->price ?? 0,
                    'worst_price' => $worstOffer->price ?? 0,
                    'average_price' => round($averagePrice, 2),
                    'potential_savings' => round($potentialSavings, 2),
                    'currency' => $bestOffer->currency ?? 'USD'
                ],
                'statistics' => [
                    'total_offers' => $sortedOffers->count(),
                    'in_stock_offers' => $inStockOffers->count(),
                    'new_condition_offers' => $newConditionOffers->count()
                ],
                'offers' => $sortedOffers->map(function ($offer) use ($bestOffer) {
                    return [
                        'retailer' => $offer->retailer,
                        'price' => $offer->price,
                        'availability' => $offer->availability,
                        'condition' => $offer->condition,
                        'shipping_cost' => $offer->shippingCost,
                        'url' => $offer->url,
                        'is_best_price' => $offer->price === $bestOffer->price,
                        'total_cost' => ($offer->price ?? 0) + ($offer->shippingCost ?? 0)
                    ];
                })->toArray(),
                'credits_remaining' => $offers->creditsRemaining
            ]);
            
        } catch (ShopSavvyException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
```

#### Advanced Price Analysis
```php
function analyzeOffers(ShopSavvyClient $client, string $identifier): array
{
    try {
        $response = $client->getCurrentOffers($identifier);
        $offers = $response->data;
        
        echo "Found " . count($offers) . " offers across retailers\n";
        
        // Filter valid prices and sort
        $validOffers = array_filter($offers, fn($offer) => $offer->price !== null);
        usort($validOffers, fn($a, $b) => $a->price <=> $b->price);
        
        if (empty($validOffers)) {
            return ['error' => 'No valid offers found'];
        }
        
        $cheapest = $validOffers[0];
        $mostExpensive = end($validOffers);
        
        $prices = array_column($validOffers, 'price');
        $average = array_sum($prices) / count($prices);
        
        echo "💰 Best price: {$cheapest->retailer} - $" . number_format($cheapest->price, 2) . "\n";
        echo "💸 Highest price: {$mostExpensive->retailer} - $" . number_format($mostExpensive->price, 2) . "\n";
        echo "📊 Average price: $" . number_format($average, 2) . "\n";
        echo "💡 Potential savings: $" . number_format($mostExpensive->price - $cheapest->price, 2) . "\n";
        
        // Additional analysis
        $inStockOffers = array_filter($offers, fn($offer) => $offer->availability === 'in_stock');
        $newConditionOffers = array_filter($offers, fn($offer) => $offer->condition === 'new');
        
        echo "✅ In-stock offers: " . count($inStockOffers) . "\n";
        echo "🆕 New condition: " . count($newConditionOffers) . "\n";
        
        return [
            'cheapest' => $cheapest,
            'most_expensive' => $mostExpensive,
            'average' => $average,
            'savings' => $mostExpensive->price - $cheapest->price,
            'total_offers' => count($offers),
            'in_stock_count' => count($inStockOffers),
            'new_condition_count' => count($newConditionOffers)
        ];
        
    } catch (ShopSavvyException $e) {
        echo "Error analyzing offers: " . $e->getMessage() . "\n";
        return ['error' => $e->getMessage()];
    }
}
```

#### Retailer-Specific Queries
```php
// Compare prices across major retailers
$retailers = ['amazon', 'walmart', 'target', 'bestbuy'];
$retailerPrices = [];

foreach ($retailers as $retailer) {
    try {
        $offers = $client->getCurrentOffers('012345678901', $retailer);
        
        if (!empty($offers->data)) {
            // Find best offer for this retailer
            $bestPrice = min(array_column($offers->data, 'price'));
            $retailerPrices[$retailer] = $bestPrice;
        }
    } catch (ShopSavvyException $e) {
        echo "Error fetching {$retailer} prices: " . $e->getMessage() . "\n";
        continue;
    }
}

// Sort and display results
asort($retailerPrices);

echo "Retailer price comparison:\n";
foreach ($retailerPrices as $retailer => $price) {
    echo "  " . ucfirst($retailer) . ": $" . number_format($price, 2) . "\n";
}
```

## 🚀 Production Deployment

### Laravel Service Integration

```php
<?php
// app/Services/PriceMonitoringService.php

namespace App\Services;

use App\Models\ProductWatch;
use App\Models\PriceAlert;
use App\Notifications\PriceDropNotification;
use ShopSavvy\SDK\ShopSavvyClient;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PriceMonitoringService
{
    private ShopSavvyClient $client;
    
    public function __construct()
    {
        $this->client = new ShopSavvyClient(config('shopsavvy.api_key'));
    }
    
    public function addProductWatch(string $identifier, float $targetPrice, int $userId): ProductWatch
    {
        // Get product details first
        $product = $this->client->getProductDetails($identifier);
        
        // Schedule monitoring with API
        $this->client->scheduleProductMonitoring($identifier, 'daily');
        
        // Store in local database
        return ProductWatch::create([
            'user_id' => $userId,
            'product_identifier' => $identifier,
            'product_name' => $product->data->name,
            'product_brand' => $product->data->brand,
            'target_price' => $targetPrice,
            'is_active' => true
        ]);
    }
    
    public function checkPriceDrops(): void
    {
        $activeWatches = ProductWatch::where('is_active', true)->get();
        
        foreach ($activeWatches as $watch) {
            try {
                // Check cache first to avoid rate limiting
                $cacheKey = "price_check_{$watch->product_identifier}";
                
                if (Cache::has($cacheKey)) {
                    continue;
                }
                
                $offers = $this->client->getCurrentOffers($watch->product_identifier);
                $bestOffer = collect($offers->data)
                    ->filter(fn($offer) => $offer->price !== null)
                    ->sortBy('price')
                    ->first();
                
                if ($bestOffer && $bestOffer->price <= $watch->target_price) {
                    // Create price alert
                    $alert = PriceAlert::create([
                        'product_watch_id' => $watch->id,
                        'price' => $bestOffer->price,
                        'retailer' => $bestOffer->retailer,
                        'url' => $bestOffer->url,
                        'triggered_at' => now()
                    ]);
                    
                    // Send notification to user
                    $watch->user->notify(new PriceDropNotification($alert));
                    
                    Log::info("Price alert triggered", [
                        'product' => $watch->product_name,
                        'target' => $watch->target_price,
                        'actual' => $bestOffer->price,
                        'retailer' => $bestOffer->retailer
                    ]);
                }
                
                // Cache for 1 hour to prevent excessive API calls
                Cache::put($cacheKey, true, now()->addHour());
                
            } catch (ShopSavvyException $e) {
                Log::error("Price check failed", [
                    'product_identifier' => $watch->product_identifier,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Rate limiting - sleep between requests
            usleep(500000); // 0.5 seconds
        }
    }
    
    public function getMarketAnalysis(string $identifier): array
    {
        $offers = $this->client->getCurrentOffers($identifier);
        $history = $this->client->getPriceHistory(
            $identifier,
            now()->subDays(30)->format('Y-m-d'),
            now()->format('Y-m-d')
        );
        
        return [
            'current_analysis' => $this->analyzePrices($offers->data),
            'historical_trends' => $this->analyzeHistory($history->data),
            'recommendations' => $this->generateRecommendations($offers->data, $history->data)
        ];
    }
    
    private function analyzePrices(array $offers): array
    {
        $validOffers = array_filter($offers, fn($offer) => $offer->price !== null);
        
        if (empty($validOffers)) {
            return ['error' => 'No valid offers found'];
        }
        
        $prices = array_column($validOffers, 'price');
        sort($prices);
        
        return [
            'min_price' => min($prices),
            'max_price' => max($prices),
            'avg_price' => array_sum($prices) / count($prices),
            'median_price' => $this->median($prices),
            'price_range' => max($prices) - min($prices),
            'total_retailers' => count($validOffers),
            'in_stock_count' => count(array_filter($validOffers, fn($o) => $o->availability === 'in_stock'))
        ];
    }
    
    private function median(array $numbers): float
    {
        $count = count($numbers);
        $middle = floor(($count - 1) / 2);
        
        if ($count % 2) {
            return $numbers[$middle];
        } else {
            return ($numbers[$middle] + $numbers[$middle + 1]) / 2;
        }
    }
}
```

### WordPress Plugin Integration

```php
<?php
// wp-content/plugins/shopsavvy-price-tracker/shopsavvy-price-tracker.php

/**
 * Plugin Name: ShopSavvy Price Tracker
 * Description: Track product prices and get alerts when prices drop
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use ShopSavvy\SDK\ShopSavvyClient;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;

class ShopSavvyPriceTracker
{
    private ShopSavvyClient $client;
    
    public function __construct()
    {
        $this->client = new ShopSavvyClient(get_option('shopsavvy_api_key'));
        
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_shopsavvy_search_product', [$this, 'ajaxSearchProduct']);
        add_action('wp_ajax_shopsavvy_get_offers', [$this, 'ajaxGetOffers']);
        add_shortcode('shopsavvy_price_comparison', [$this, 'priceComparisonShortcode']);
    }
    
    public function init(): void
    {
        // Register custom post type for tracked products
        register_post_type('tracked_product', [
            'public' => true,
            'label' => 'Tracked Products',
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-chart-line'
        ]);
    }
    
    public function ajaxSearchProduct(): void
    {
        check_ajax_referer('shopsavvy_nonce');
        
        $identifier = sanitize_text_field($_POST['identifier'] ?? '');
        
        if (empty($identifier)) {
            wp_die(json_encode(['error' => 'Product identifier required']));
        }
        
        try {
            $product = $this->client->getProductDetails($identifier);
            $offers = $this->client->getCurrentOffers($identifier);
            
            $bestOffer = null;
            $minPrice = PHP_FLOAT_MAX;
            
            foreach ($offers->data as $offer) {
                if ($offer->price && $offer->price < $minPrice) {
                    $minPrice = $offer->price;
                    $bestOffer = $offer;
                }
            }
            
            wp_send_json_success([
                'product' => [
                    'id' => $product->data->id,
                    'name' => $product->data->name,
                    'brand' => $product->data->brand,
                    'category' => $product->data->category,
                    'image' => $product->data->images[0] ?? null
                ],
                'best_offer' => $bestOffer ? [
                    'retailer' => $bestOffer->retailer,
                    'price' => $bestOffer->price,
                    'url' => $bestOffer->url,
                    'availability' => $bestOffer->availability
                ] : null,
                'total_offers' => count($offers->data)
            ]);
            
        } catch (ShopSavvyException $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function priceComparisonShortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'identifier' => '',
            'show_chart' => 'true'
        ], $atts);
        
        if (empty($atts['identifier'])) {
            return '<p>Product identifier required</p>';
        }
        
        try {
            $product = $this->client->getProductDetails($atts['identifier']);
            $offers = $this->client->getCurrentOffers($atts['identifier']);
            
            ob_start();
            ?>
            <div class="shopsavvy-price-comparison" data-identifier="<?= esc_attr($atts['identifier']) ?>">
                <h3><?= esc_html($product->data->name) ?></h3>
                
                <?php if ($product->data->brand): ?>
                    <p class="brand">Brand: <?= esc_html($product->data->brand) ?></p>
                <?php endif; ?>
                
                <div class="offers-grid">
                    <?php foreach ($offers->data as $offer): ?>
                        <?php if ($offer->price): ?>
                            <div class="offer-card">
                                <h4><?= esc_html($offer->retailer) ?></h4>
                                <div class="price">$<?= number_format($offer->price, 2) ?></div>
                                
                                <?php if ($offer->availability): ?>
                                    <div class="availability <?= esc_attr($offer->availability) ?>">
                                        <?= esc_html(ucfirst(str_replace('_', ' ', $offer->availability))) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($offer->url): ?>
                                    <a href="<?= esc_url($offer->url) ?>" target="_blank" class="view-offer">
                                        View Offer
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <style>
            .shopsavvy-price-comparison {
                margin: 20px 0;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 8px;
            }
            
            .offers-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-top: 20px;
            }
            
            .offer-card {
                border: 1px solid #eee;
                padding: 15px;
                border-radius: 5px;
                text-align: center;
            }
            
            .offer-card .price {
                font-size: 24px;
                font-weight: bold;
                color: #2c5aa0;
                margin: 10px 0;
            }
            
            .availability.in_stock {
                color: #46b450;
                font-weight: bold;
            }
            
            .availability.out_of_stock {
                color: #dc3232;
            }
            
            .view-offer {
                display: inline-block;
                margin-top: 10px;
                padding: 8px 16px;
                background: #2c5aa0;
                color: white;
                text-decoration: none;
                border-radius: 4px;
            }
            </style>
            <?php
            return ob_get_clean();
            
        } catch (ShopSavvyException $e) {
            return '<p>Error loading product data: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
}

new ShopSavvyPriceTracker();
```

### Symfony Service Configuration

```php
<?php
// src/Service/ShopSavvyService.php

namespace App\Service;

use ShopSavvy\SDK\ShopSavvyClient;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

class ShopSavvyService
{
    private ShopSavvyClient $client;
    
    public function __construct(
        #[Autowire('%env(SHOPSAVVY_API_KEY)%')] string $apiKey,
        private LoggerInterface $logger
    ) {
        $this->client = new ShopSavvyClient($apiKey);
    }
    
    public function compareProductPrices(string $identifier): array
    {
        try {
            $product = $this->client->getProductDetails($identifier);
            $offers = $this->client->getCurrentOffers($identifier);
            
            $validOffers = array_filter($offers->data, fn($offer) => $offer->price !== null);
            usort($validOffers, fn($a, $b) => $a->price <=> $b->price);
            
            return [
                'product' => $product->data,
                'offers' => $validOffers,
                'best_price' => $validOffers[0]->price ?? null,
                'price_range' => [
                    'min' => $validOffers[0]->price ?? null,
                    'max' => end($validOffers)->price ?? null
                ],
                'statistics' => [
                    'total_offers' => count($validOffers),
                    'average_price' => $validOffers ? array_sum(array_column($validOffers, 'price')) / count($validOffers) : 0
                ]
            ];
            
        } catch (ShopSavvyException $e) {
            $this->logger->error('ShopSavvy API error', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
```

## Exception Handling

The SDK provides comprehensive exception handling with specific exception types:

```php
use ShopSavvy\SDK\Exceptions\{
    ShopSavvyAuthenticationException,
    ShopSavvyNotFoundException,
    ShopSavvyValidationException,
    ShopSavvyRateLimitException,
    ShopSavvyNetworkException,
    ShopSavvyException
};

try {
    $response = $client->getProductDetails('012345678901');
    // Handle success
    
} catch (ShopSavvyAuthenticationException $e) {
    // Invalid API key or authentication failure
    error_log("🔐 Authentication failed: " . $e->getMessage());
    
} catch (ShopSavvyNotFoundException $e) {
    // Product not found in database
    error_log("❌ Product not found: " . $e->getMessage());
    
} catch (ShopSavvyValidationException $e) {
    // Invalid parameters provided
    error_log("⚠️ Invalid parameters: " . $e->getMessage());
    
} catch (ShopSavvyRateLimitException $e) {
    // Rate limit exceeded
    error_log("🚦 Rate limit exceeded: " . $e->getMessage());
    
    // Get retry delay if available
    $retryAfter = $e->getRetryAfter(); // seconds
    if ($retryAfter) {
        error_log("Retry after: {$retryAfter} seconds");
    }
    
} catch (ShopSavvyNetworkException $e) {
    // Network connectivity issues
    error_log("🌐 Network error: " . $e->getMessage());
    
} catch (ShopSavvyException $e) {
    // Generic API error
    error_log("❌ API error: " . $e->getMessage());
    
} catch (Exception $e) {
    // Unexpected errors
    error_log("💥 Unexpected error: " . $e->getMessage());
}
```

### Retry Logic with Exponential Backoff

```php
function retryWithBackoff(callable $operation, int $maxAttempts = 3): mixed
{
    $attempt = 1;
    $delay = 1; // Initial delay in seconds
    
    while ($attempt <= $maxAttempts) {
        try {
            return $operation();
            
        } catch (ShopSavvyRateLimitException $e) {
            if ($attempt === $maxAttempts) {
                throw $e;
            }
            
            // Use server-specified retry delay if available
            $retryDelay = $e->getRetryAfter() ?? $delay;
            error_log("Rate limited, retrying in {$retryDelay} seconds...");
            sleep($retryDelay);
            
        } catch (ShopSavvyNetworkException $e) {
            if ($attempt === $maxAttempts) {
                throw $e;
            }
            
            error_log("Network error, retrying in {$delay} seconds...");
            sleep($delay);
            
        }
        
        $attempt++;
        $delay *= 2; // Exponential backoff
    }
}

// Usage example
$product = retryWithBackoff(function() use ($client) {
    return $client->getProductDetails('012345678901');
});
```

## 🛠️ Development & Testing

### Local Development Setup

```bash
# Clone the repository
git clone https://github.com/shopsavvy/sdk-php.git
cd sdk-php

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan

# Run code formatting
composer format
```

### Testing Your Integration

```php
<?php
// tests/IntegrationTest.php

require_once 'vendor/autoload.php';

use ShopSavvy\SDK\ShopSavvyClient;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;

class SDKTester
{
    private ShopSavvyClient $client;
    
    public function __construct()
    {
        // Use test API key (starts with ss_test_)
        $this->client = new ShopSavvyClient('ss_test_your_test_key_here');
    }
    
    public function runTests(): void
    {
        try {
            // Test product lookup
            $product = $this->client->getProductDetails('012345678901');
            echo "✅ Product lookup: {$product->data->name}\n";
            
            // Test current offers
            $offers = $this->client->getCurrentOffers('012345678901');
            echo "✅ Current offers: " . count($offers->data) . " found\n";
            
            // Test usage info
            $usage = $this->client->getUsage();
            echo "✅ API usage: " . ($usage->data->creditsRemaining ?? 0) . " credits remaining\n";
            
            echo "\n🎉 All tests passed! SDK is working correctly.\n";
            
        } catch (ShopSavvyException $e) {
            echo "❌ Test failed: " . $e->getMessage() . "\n";
        }
    }
}

$tester = new SDKTester();
$tester->runTests();
```

## Data Models

All data is returned as strongly-typed PHP 8.0+ objects with full type hints:

### ProductDetails
```php
class ProductDetails 
{
    public readonly string $id;
    public readonly string $name;
    public readonly ?string $description;
    public readonly ?string $brand;
    public readonly ?string $category;
    public readonly ?string $upc;
    public readonly ?string $asin;
    public readonly ?string $modelNumber;
    public readonly array $images;
    public readonly array $specifications;
    public readonly ?string $createdAt;
    public readonly ?string $updatedAt;
    
    // Helper methods
    public function hasImages(): bool
    {
        return !empty($this->images);
    }
    
    public function getDisplayName(): string
    {
        return trim($this->brand . ' ' . $this->name);
    }
    
    public function getMainImage(): ?string
    {
        return $this->images[0] ?? null;
    }
}
```

### Offer
```php
class Offer 
{
    public readonly string $retailer;
    public readonly ?float $price;
    public readonly string $currency;
    public readonly ?string $availability;
    public readonly ?string $condition;
    public readonly ?float $shippingCost;
    public readonly ?string $url;
    public readonly ?string $lastUpdated;
    
    // Helper methods
    public function isInStock(): bool
    {
        return $this->availability === 'in_stock';
    }
    
    public function isNewCondition(): bool
    {
        return $this->condition === 'new';
    }
    
    public function getTotalCost(): float
    {
        return ($this->price ?? 0) + ($this->shippingCost ?? 0);
    }
    
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price ?? 0, 2);
    }
}
```

### UsageInfo
```php
class UsageInfo 
{
    public readonly ?int $creditsUsed;
    public readonly ?int $creditsRemaining;
    public readonly ?int $creditsLimit;
    public readonly ?string $resetDate;
    public readonly ?string $currentPeriodStart;
    public readonly ?string $currentPeriodEnd;
    
    // Helper methods
    public function getUsagePercentage(): float
    {
        $used = $this->creditsUsed ?? 0;
        $limit = $this->creditsLimit ?? 1;
        return ($used / $limit) * 100;
    }
    
    public function isNearLimit(float $threshold = 80.0): bool
    {
        return $this->getUsagePercentage() > $threshold;
    }
    
    public function getDaysUntilReset(): ?int
    {
        if (!$this->resetDate) {
            return null;
        }
        
        $resetTime = strtotime($this->resetDate);
        $currentTime = time();
        
        return max(0, ceil(($resetTime - $currentTime) / 86400));
    }
}
```

## 📚 Additional Resources

- **[ShopSavvy Data API Documentation](https://shopsavvy.com/data/documentation)** - Complete API reference
- **[API Dashboard](https://shopsavvy.com/data/dashboard)** - Manage your API keys and usage
- **[GitHub Repository](https://github.com/shopsavvy/sdk-php)** - Source code and issues
- **[Packagist](https://packagist.org/packages/shopsavvy/shopsavvy-sdk-php)** - Package releases and stats
- **[PHP Documentation](https://www.php.net/docs.php)** - PHP language reference
- **[Laravel Documentation](https://laravel.com/docs)** - Laravel framework guide
- **[Symfony Documentation](https://symfony.com/doc/current/index.html)** - Symfony framework guide
- **[Support](mailto:business@shopsavvy.com)** - Get help from our team

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details on:

- Reporting bugs and feature requests
- Setting up development environment  
- Submitting pull requests
- Code standards and testing
- PHP best practices and PSR compliance

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🏢 About ShopSavvy

**ShopSavvy** is the world's first mobile shopping app, helping consumers find the best deals since 2008. With over **40 million downloads** and millions of active users, ShopSavvy has saved consumers billions of dollars.

### Our Data API Powers:
- 🛒 **E-commerce platforms** with competitive intelligence  
- 📊 **Market research** with real-time pricing data
- 🏪 **Retailers** with inventory and pricing optimization
- 📱 **Mobile apps** with product lookup and price comparison
- 🤖 **Business intelligence** with automated price monitoring

### Why Choose ShopSavvy Data API?
- ✅ **Trusted by millions** - Proven at scale since 2008
- ✅ **Comprehensive coverage** - 1000+ retailers, millions of products  
- ✅ **Real-time accuracy** - Fresh data updated continuously
- ✅ **Developer-friendly** - Easy integration, great documentation
- ✅ **Reliable infrastructure** - 99.9% uptime, enterprise-grade
- ✅ **Flexible pricing** - Plans for every use case and budget

### Perfect for PHP Development:
- 🚀 **Framework agnostic** - Works with Laravel, Symfony, WordPress, and vanilla PHP
- 🔄 **Modern PHP 8.0+** - Type hints, readonly properties, and latest features
- 🛡️ **Robust error handling** - Comprehensive exception hierarchy
- ⚡ **Production ready** - Built-in retry logic, rate limiting, and caching support
- 🧪 **Well tested** - Comprehensive test suite with PHPUnit and PHPStan

---

**Ready to get started?** [Sign up for your API key](https://shopsavvy.com/data) • **Need help?** [Contact us](mailto:business@shopsavvy.com)