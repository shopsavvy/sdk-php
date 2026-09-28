<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Request model for scheduling product monitoring
 *
 * @deprecated Not used by the client and not a shape the API sends or reads:
 *   the endpoint reads only the query parameters `ids`, `schedule` and `retailer`; scheduleProductMonitoring() sends them.
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
