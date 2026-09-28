<?php

declare(strict_types=1);

namespace ShopSavvy\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;
use ShopSavvy\SDK\Exceptions\ShopSavvyAuthenticationException;
use ShopSavvy\SDK\Exceptions\ShopSavvyNotFoundException;
use ShopSavvy\SDK\Exceptions\ShopSavvyValidationException;
use ShopSavvy\SDK\Exceptions\ShopSavvyRateLimitException;
use ShopSavvy\SDK\Exceptions\ShopSavvyNetworkException;
use ShopSavvy\SDK\Models\ApiResponse;
use ShopSavvy\SDK\Models\ProductSearchResult;
use ShopSavvy\SDK\Models\ProductDetails;
use ShopSavvy\SDK\Models\ProductWithOffers;
use ShopSavvy\SDK\Models\Offer;
use ShopSavvy\SDK\Models\OfferWithHistory;
use ShopSavvy\SDK\Models\ScheduleResponse;
use ShopSavvy\SDK\Models\ScheduledProduct;
use ShopSavvy\SDK\Models\RemoveResponse;
use ShopSavvy\SDK\Models\UsageInfo;

/**
 * Official PHP client for ShopSavvy Data API
 *
 * Provides access to product data, pricing information, and price history
 * across thousands of retailers and millions of products.
 *
 * Example usage:
 * ```php
 * $client = new ShopSavvyClient('ss_live_your_api_key_here');
 *
 * try {
 *     $product = $client->getProductDetails('012345678901');
 *     echo "Product: " . $product->data[0]->title . "\n";
 * } catch (ShopSavvyException $e) {
 *     echo "Error: " . $e->getMessage() . "\n";
 * }
 * ```
 */
class ShopSavvyClient
{
    public const VERSION = '1.3.0';
    private const DEFAULT_BASE_URL = 'https://api.shopsavvy.com/v1';
    private const API_KEY_PATTERN = '/^ss_(live|test)_[a-zA-Z0-9]+$/';

    private Client $httpClient;
    private string $baseUrl;

    /**
     * Create a new ShopSavvy Data API client
     *
     * @param string $apiKey Your Data API key (ss_live_... or ss_test_...)
     * @param string|null $baseUrl Override the API base URL (defaults to https://api.shopsavvy.com/v1)
     * @param float $timeout Request timeout in seconds
     * @param array<string, mixed> $httpOptions Extra Guzzle client options (e.g. 'proxy', 'handler'),
     *   merged over the SDK defaults. The Authorization and User-Agent headers are always set by the SDK.
     */
    public function __construct(
        private string $apiKey,
        ?string $baseUrl = null,
        float $timeout = 30.0,
        array $httpOptions = []
    ) {
        if (empty(trim($this->apiKey))) {
            throw new \InvalidArgumentException('API key is required. Get one at https://shopsavvy.com/data');
        }

        if (!preg_match(self::API_KEY_PATTERN, $this->apiKey)) {
            throw new \InvalidArgumentException('Invalid API key format. API keys should start with ss_live_ or ss_test_');
        }

        $this->baseUrl = $baseUrl ?? self::DEFAULT_BASE_URL;

        $config = array_replace_recursive(['timeout' => $timeout], $httpOptions);
        $config['headers'] = array_merge($config['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'ShopSavvy-PHP-SDK/' . self::VERSION,
        ]);

        $this->httpClient = new Client($config);
    }

    // MARK: - Search

    /**
     * Search for products by keyword
     *
     * @param string $query Search query or keyword
     * @param int|null $limit Optional maximum number of results
     * @param int|null $offset Optional pagination offset
     * @return ProductSearchResult Search results with pagination
     * @throws ShopSavvyException if the API request fails
     */
    public function searchProducts(string $query, ?int $limit = null, ?int $offset = null): ProductSearchResult
    {
        $queryParams = ['q' => $query];
        if ($limit !== null) {
            $queryParams['limit'] = (string) $limit;
        }
        if ($offset !== null) {
            $queryParams['offset'] = (string) $offset;
        }

        $response = $this->executeRequestRaw('GET', '/products/search', $queryParams);
        return ProductSearchResult::fromArray($response);
    }

    // MARK: - Product Details

    /**
     * Look up product details by identifier
     *
     * @param string $identifier Product identifier (barcode, ASIN, URL, model number, or ShopSavvy product ID)
     * @param string|null $format Response format ('json' or 'csv')
     * @return ApiResponse Product details
     * @throws ShopSavvyException if the API request fails
     */
    public function getProductDetails(string $identifier, ?string $format = null): ApiResponse
    {
        $query = ['ids' => $identifier];
        if ($format !== null) {
            $query['format'] = $format;
        }

        return $this->executeRequest('GET', '/products', $query);
    }

    /**
     * Look up details for multiple products
     *
     * @param array<string> $identifiers List of product identifiers
     * @param string|null $format Response format ('json' or 'csv')
     * @return ApiResponse List of product details
     * @throws ShopSavvyException if the API request fails
     */
    public function getProductDetailsBatch(array $identifiers, ?string $format = null): ApiResponse
    {
        $query = ['ids' => implode(',', $identifiers)];
        if ($format !== null) {
            $query['format'] = $format;
        }

        return $this->executeRequest('GET', '/products', $query);
    }

    // MARK: - Current Offers

    /**
     * Get current offers for a product
     *
     * @param string $identifier Product identifier
     * @param string|null $retailer Optional retailer to filter by
     * @param string|null $format Response format ('json' or 'csv')
     * @return ApiResponse Current offers
     * @throws ShopSavvyException if the API request fails
     */
    public function getCurrentOffers(string $identifier, ?string $retailer = null, ?string $format = null): ApiResponse
    {
        $query = ['ids' => $identifier];
        if ($retailer !== null) {
            $query['retailer'] = $retailer;
        }
        if ($format !== null) {
            $query['format'] = $format;
        }

        return $this->executeRequest('GET', '/products/offers', $query);
    }

    /**
     * Get current offers for multiple products
     *
     * @param array<string> $identifiers List of product identifiers
     * @param string|null $retailer Optional retailer to filter by
     * @param string|null $format Response format ('json' or 'csv')
     * @return ApiResponse Map of identifiers to their offers
     * @throws ShopSavvyException if the API request fails
     */
    public function getCurrentOffersBatch(array $identifiers, ?string $retailer = null, ?string $format = null): ApiResponse
    {
        $query = ['ids' => implode(',', $identifiers)];
        if ($retailer !== null) {
            $query['retailer'] = $retailer;
        }
        if ($format !== null) {
            $query['format'] = $format;
        }

        return $this->executeRequest('GET', '/products/offers', $query);
    }

    // MARK: - Price History

    /**
     * Get price history for a product
     *
     * @param string $identifier Product identifier
     * @param string $startDate Start date (YYYY-MM-DD format)
     * @param string $endDate End date (YYYY-MM-DD format)
     * @param string|null $retailer Optional retailer to filter by
     * @param string|null $format Response format ('json' or 'csv')
     * @return ApiResponse Offers with price history
     * @throws ShopSavvyException if the API request fails
     */
    public function getPriceHistory(
        string $identifier,
        string $startDate,
        string $endDate,
        ?string $retailer = null,
        ?string $format = null
    ): ApiResponse {
        // Wire params are 'start'/'end' — what GET /products/offers/history
        // reads, and what the OpenAPI spec and public docs document. The old
        // 'start_date'/'end_date' names came from the MCP tool's argument
        // convention (a different interface entirely) and 400'd every call.
        $query = [
            'ids' => $identifier,
            'start' => $startDate,
            'end' => $endDate,
        ];
        if ($retailer !== null) {
            $query['retailer'] = $retailer;
        }
        if ($format !== null) {
            $query['format'] = $format;
        }

        return $this->executeRequest('GET', '/products/offers/history', $query);
    }

    // MARK: - Monitoring

    /**
     * Schedule product monitoring
     *
     * Sends PUT /products/scheduled?ids=&schedule=&retailer=. The endpoint reads
     * query parameters only; a JSON body (what this method used to send) is
     * ignored, so every call failed with "'ids' is required".
     *
     * @param string $identifier Product identifier
     * @param string $frequency How often to refresh ('hourly', 'daily', 'weekly')
     * @param string|null $retailer Optional retailer to monitor
     * @return ApiResponse The scheduled products (each product with its `schedule`)
     * @throws ShopSavvyException if the API request fails
     */
    public function scheduleProductMonitoring(string $identifier, string $frequency, ?string $retailer = null): ApiResponse
    {
        $query = [
            'ids' => $identifier,
            'schedule' => $frequency,
        ];
        if ($retailer !== null) {
            $query['retailer'] = $retailer;
        }

        return $this->executeRequest('PUT', '/products/scheduled', $query);
    }

    /**
     * Get all scheduled products
     *
     * @return ApiResponse List of scheduled products
     * @throws ShopSavvyException if the API request fails
     */
    public function getScheduledProducts(): ApiResponse
    {
        return $this->executeRequest('GET', '/products/scheduled');
    }

    /**
     * Remove product from monitoring schedule
     *
     * Sends DELETE /products/scheduled?ids= (query parameters, like scheduling).
     *
     * @param string $identifier Product identifier to remove
     * @return ApiResponse Removal confirmation
     * @throws ShopSavvyException if the API request fails
     */
    public function removeProductFromSchedule(string $identifier): ApiResponse
    {
        return $this->executeRequest('DELETE', '/products/scheduled', ['ids' => $identifier]);
    }

    // MARK: - Usage

    /**
     * Get API usage information
     *
     * @return ApiResponse Current usage and credit information
     * @throws ShopSavvyException if the API request fails
     */
    public function getUsage(): ApiResponse
    {
        return $this->executeRequest('GET', '/usage');
    }

    /**
     * Browse current shopping deals
     *
     * @param array<string, mixed> $params Query parameters (sort, limit, offset, category, retailer, tag, min_price, max_price, grade)
     * @return array<string, mixed> Deals response
     */
    public function getDeals(array $params = []): array
    {
        return $this->executeRequestRaw('GET', '/deals', $params);
    }

    /**
     * Get TLDR review for a product
     *
     * @param string $identifier Product identifier (barcode, ASIN, URL, model number)
     * @return array<string, mixed> Review response
     */
    public function getProductReview(string $identifier): array
    {
        return $this->executeRequestRaw('GET', '/products/reviews', ['id' => $identifier]);
    }

    /**
     * Look up multiple products at once (sync for <=20, async for >20)
     *
     * @param array<string> $identifiers Product identifiers (max 100)
     * @param array<string>|null $include Optional extras: ["offers"], ["reviews"]
     * @return array<string, mixed> Batch results
     */
    public function batchLookup(array $identifiers, ?array $include = null): array
    {
        $body = ['identifiers' => $identifiers];
        if ($include !== null) $body['include'] = $include;
        return $this->executeRequestRaw('POST', '/products/batch', [], $body);
    }

    /**
     * Poll for async batch job results
     *
     * @return array<string, mixed>
     */
    public function getBatchStatus(string $batchId): array
    {
        return $this->executeRequestRaw('GET', "/batch/{$batchId}");
    }

    /**
     * @param array<int, string> $events e.g. ['price_drop', 'availability_change', 'schedule_completion']
     * @return array<string, mixed>
     */
    public function createWebhook(string $url, array $events): array
    {
        return $this->executeRequestRaw('POST', '/webhooks', [], ['url' => $url, 'events' => $events]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listWebhooks(): array
    {
        return $this->executeRequestRaw('GET', '/webhooks');
    }

    /**
     * @return array<string, mixed>
     */
    public function testWebhook(string $webhookId): array
    {
        return $this->executeRequestRaw('POST', "/webhooks/{$webhookId}/test");
    }

    /**
     * Update a webhook. All parameters are optional, but at least one of
     * $url, $events, or $isActive must be provided.
     *
     * @param string $webhookId
     * @param string|null $url
     * @param array<int, string>|null $events
     * @param bool|null $isActive
     * @return array<string, mixed>
     * @throws ShopSavvyException
     */
    public function updateWebhook(string $webhookId, ?string $url = null, ?array $events = null, ?bool $isActive = null): array
    {
        if ($url === null && $events === null && $isActive === null) {
            throw new \InvalidArgumentException('updateWebhook requires at least one of $url, $events, or $isActive');
        }
        $body = [];
        if ($url !== null) $body['url'] = $url;
        if ($events !== null) $body['events'] = $events;
        if ($isActive !== null) $body['is_active'] = $isActive;
        return $this->executeRequestRaw('PUT', "/webhooks/{$webhookId}", [], $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteWebhook(string $webhookId): array
    {
        return $this->executeRequestRaw('DELETE', "/webhooks/{$webhookId}");
    }

    // MARK: - Private Methods

    /**
     * Execute an HTTP request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array<string, string> $query Query parameters
     * @param array<string, mixed>|null $body Request body
     * @return ApiResponse API response
     * @throws ShopSavvyException if the request fails
     */
    private function executeRequest(string $method, string $endpoint, array $query = [], ?array $body = null): ApiResponse
    {
        $data = $this->executeRequestRaw($method, $endpoint, $query, $body);
        return ApiResponse::fromArray($data);
    }

    /**
     * Execute an HTTP request and return raw array
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array<string, string> $query Query parameters
     * @param array<string, mixed>|null $body Request body
     * @return array<string, mixed> Raw response data
     * @throws ShopSavvyException if the request fails
     */
    private function executeRequestRaw(string $method, string $endpoint, array $query = [], ?array $body = null): array
    {
        $url = $this->baseUrl . $endpoint;

        $options = [];

        if (!empty($query)) {
            $options['query'] = $query;
        }

        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw $this->createExceptionFromResponse($statusCode, $responseBody);
            }

            $data = json_decode($responseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ShopSavvyException('Failed to decode JSON response: ' . json_last_error_msg());
            }

            return $data;

        } catch (RequestException $e) {
            $errorResponse = $e->getResponse();
            if ($errorResponse !== null) {
                throw $this->createExceptionFromResponse(
                    $errorResponse->getStatusCode(),
                    (string) $errorResponse->getBody()
                );
            }

            throw new ShopSavvyNetworkException('Network error: ' . $e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            throw new ShopSavvyNetworkException('HTTP client error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create an appropriate exception from an HTTP response
     */
    private function createExceptionFromResponse(int $statusCode, string $responseBody): ShopSavvyException
    {
        $errorMessage = 'Unknown error';

        $data = json_decode($responseBody, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['error'])) {
            $errorMessage = $data['error'];
        } elseif (!empty($responseBody)) {
            $errorMessage = $responseBody;
        }

        return match ($statusCode) {
            401 => new ShopSavvyAuthenticationException("Authentication failed: $errorMessage", $statusCode),
            404 => new ShopSavvyNotFoundException("Not found: $errorMessage", $statusCode),
            400, 422 => new ShopSavvyValidationException("Invalid request: $errorMessage", $statusCode),
            429 => new ShopSavvyRateLimitException("Rate limit exceeded: $errorMessage", $statusCode),
            default => new ShopSavvyException("HTTP $statusCode: $errorMessage", $statusCode),
        };
    }
}
