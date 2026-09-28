<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

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
