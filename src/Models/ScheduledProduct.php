<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Scheduled product model
 */
class ScheduledProduct
{
    public function __construct(
        public readonly string $productId,
        public readonly string $identifier,
        public readonly string $frequency,
        public readonly ?string $retailer = null,
        public readonly string $createdAt = '',
        public readonly ?string $lastRefreshed = null
    ) {
    }

    /**
     * Create ScheduledProduct from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['product_id'],
            $data['identifier'],
            $data['frequency'],
            $data['retailer'] ?? null,
            $data['created_at'],
            $data['last_refreshed'] ?? null
        );
    }
}
