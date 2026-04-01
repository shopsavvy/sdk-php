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
use ShopSavvy\SDK\Models\ScheduleRequest;
use ShopSavvy\SDK\Models\ScheduleResponse;
use ShopSavvy\SDK\Models\ScheduledProduct;
use ShopSavvy\SDK\Models\RemoveRequest;
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
    public const VERSION = '1.1.0';
    private const DEFAULT_BASE_URL = 'https://api.shopsavvy.com/v1';
    private const API_KEY_PATTERN = '/^ss_(live|test)_[a-zA-Z0-9]+$/';

    private Client $httpClient;
    private string $baseUrl;

    /**
     * Create a new ShopSavvy Data API client
     */
    public function __construct(
        private string $apiKey,
        ?string $baseUrl = null,
        float $timeout = 30.0
    ) {
        if (empty(trim($this->apiKey))) {
            throw new \InvalidArgumentException('API key is required. Get one at https://shopsavvy.com/data');
        }

        if (!preg_match(self::API_KEY_PATTERN, $this->apiKey)) {
            throw new \InvalidArgumentException('Invalid API key format. API keys should start with ss_live_ or ss_test_');
        }

        $this->baseUrl = $baseUrl ?? self::DEFAULT_BASE_URL;

        $this->httpClient = new Client([
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'ShopSavvy-PHP-SDK/' . self::VERSION,
            ],
        ]);
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
     * @return ApiResponse<array<ProductDetails>> Product details
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
     * @return ApiResponse<array<ProductDetails>> List of product details
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
     * @return ApiResponse<array<ProductWithOffers>> Current offers
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
     * @return ApiResponse<array<ProductWithOffers>> Map of identifiers to their offers
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
     * @return ApiResponse<array<OfferWithHistory>> Offers with price history
     * @throws ShopSavvyException if the API request fails
     */
    public function getPriceHistory(
        string $identifier,
        string $startDate,
        string $endDate,
        ?string $retailer = null,
        ?string $format = null
    ): ApiResponse {
        $query = [
            'ids' => $identifier,
            'start_date' => $startDate,
            'end_date' => $endDate,
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
     * @param string $identifier Product identifier
     * @param string $frequency How often to refresh ('hourly', 'daily', 'weekly')
     * @param string|null $retailer Optional retailer to monitor
     * @return ApiResponse<ScheduleResponse> Scheduling confirmation
     * @throws ShopSavvyException if the API request fails
     */
    public function scheduleProductMonitoring(string $identifier, string $frequency, ?string $retailer = null): ApiResponse
    {
        $body = [
            'identifier' => $identifier,
            'frequency' => $frequency,
        ];
        if ($retailer !== null) {
            $body['retailer'] = $retailer;
        }

        return $this->executeRequest('POST', '/products/schedule', [], $body);
    }

    /**
     * Get all scheduled products
     *
     * @return ApiResponse<array<ScheduledProduct>> List of scheduled products
     * @throws ShopSavvyException if the API request fails
     */
    public function getScheduledProducts(): ApiResponse
    {
        return $this->executeRequest('GET', '/products/scheduled');
    }

    /**
     * Remove product from monitoring schedule
     *
     * @param string $identifier Product identifier to remove
     * @return ApiResponse<RemoveResponse> Removal confirmation
     * @throws ShopSavvyException if the API request fails
     */
    public function removeProductFromSchedule(string $identifier): ApiResponse
    {
        $body = ['identifier' => $identifier];

        return $this->executeRequest('DELETE', '/products/schedule', [], $body);
    }

    // MARK: - Usage

    /**
     * Get API usage information
     *
     * @return ApiResponse<UsageInfo> Current usage and credit information
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
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                throw $this->createExceptionFromResponse($statusCode, $responseBody);
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
            401 => new ShopSavvyAuthenticationException('Authentication failed. Check your API key.'),
            404 => new ShopSavvyNotFoundException('Resource not found'),
            422 => new ShopSavvyValidationException('Request validation failed. Check your parameters.'),
            429 => new ShopSavvyRateLimitException('Rate limit exceeded. Please slow down your requests.'),
            default => new ShopSavvyException("HTTP $statusCode: $errorMessage"),
        };
    }
}
