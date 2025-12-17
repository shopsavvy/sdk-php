<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * API response metadata containing credit usage info
 */
class ApiMeta
{
    public function __construct(
        public readonly int $creditsUsed,
        public readonly int $creditsRemaining,
        public readonly ?int $rateLimitRemaining = null
    ) {
    }

    /**
     * Create ApiMeta from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['credits_used'] ?? 0,
            $data['credits_remaining'] ?? 0,
            $data['rate_limit_remaining'] ?? null
        );
    }
}

/**
 * Generic API response wrapper
 *
 * @template T
 */
class ApiResponse
{
    /**
     * @param T $data
     */
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data,
        public readonly ?string $message = null,
        public readonly ?ApiMeta $meta = null
    ) {
    }

    /**
     * Create ApiResponse from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['success'] ?? true,
            $data['data'] ?? null,
            $data['message'] ?? null,
            isset($data['meta']) ? ApiMeta::fromArray($data['meta']) : null
        );
    }

    /**
     * Get credits used from meta object
     */
    public function creditsUsed(): int
    {
        return $this->meta?->creditsUsed ?? 0;
    }

    /**
     * Get credits remaining from meta object
     */
    public function creditsRemaining(): int
    {
        return $this->meta?->creditsRemaining ?? 0;
    }
}

/**
 * Pagination info for search results
 */
class PaginationInfo
{
    public function __construct(
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
        public readonly int $returned
    ) {
    }

    /**
     * Create PaginationInfo from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['total'],
            $data['limit'],
            $data['offset'],
            $data['returned']
        );
    }
}

/**
 * Product search result with pagination
 */
class ProductSearchResult
{
    /**
     * @param array<ProductDetails> $data
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly ?PaginationInfo $pagination = null,
        public readonly ?ApiMeta $meta = null
    ) {
    }

    /**
     * Create ProductSearchResult from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $products = [];
        if (isset($data['data']) && is_array($data['data'])) {
            $products = array_map(
                fn(array $product) => ProductDetails::fromArray($product),
                $data['data']
            );
        }

        return new self(
            $data['success'] ?? true,
            $products,
            isset($data['pagination']) ? PaginationInfo::fromArray($data['pagination']) : null,
            isset($data['meta']) ? ApiMeta::fromArray($data['meta']) : null
        );
    }

    /**
     * Get credits used from meta object
     */
    public function creditsUsed(): int
    {
        return $this->meta?->creditsUsed ?? 0;
    }

    /**
     * Get credits remaining from meta object
     */
    public function creditsRemaining(): int
    {
        return $this->meta?->creditsRemaining ?? 0;
    }
}
