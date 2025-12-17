<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Request model for scheduling product monitoring
 */
class ScheduleRequest
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $frequency,
        public readonly ?string $retailer = null
    ) {
    }

    /**
     * Convert to array for JSON encoding
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'identifier' => $this->identifier,
            'frequency' => $this->frequency,
        ];

        if ($this->retailer !== null) {
            $data['retailer'] = $this->retailer;
        }

        return $data;
    }
}

/**
 * Response model for scheduling operations
 */
class ScheduleResponse
{
    public function __construct(
        public readonly bool $scheduled,
        public readonly string $productId
    ) {
    }

    /**
     * Create ScheduleResponse from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['scheduled'],
            $data['product_id']
        );
    }
}

/**
 * Response from batch scheduling
 */
class ScheduleBatchResponse
{
    public function __construct(
        public readonly string $identifier,
        public readonly bool $scheduled,
        public readonly string $productId
    ) {
    }

    /**
     * Create ScheduleBatchResponse from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['identifier'],
            $data['scheduled'],
            $data['product_id']
        );
    }
}

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

/**
 * Request model for removing scheduled products
 */
class RemoveRequest
{
    public function __construct(
        public readonly string $identifier
    ) {
    }

    /**
     * Convert to array for JSON encoding
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier];
    }
}

/**
 * Response model for removal operations
 */
class RemoveResponse
{
    public function __construct(
        public readonly bool $removed
    ) {
    }

    /**
     * Create RemoveResponse from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['removed']
        );
    }
}

/**
 * Response from batch removal
 */
class RemoveBatchResponse
{
    public function __construct(
        public readonly string $identifier,
        public readonly bool $removed
    ) {
    }

    /**
     * Create RemoveBatchResponse from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['identifier'],
            $data['removed']
        );
    }
}
