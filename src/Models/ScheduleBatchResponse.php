<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Response from batch scheduling
 *
 * @deprecated Not used by the client and not a shape the API sends or reads:
 *   scheduleProductMonitoring() returns ScheduleResponse, whose `data` lists every scheduled product.
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
