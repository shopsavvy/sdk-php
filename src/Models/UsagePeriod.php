<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Current billing period details
 */
class UsagePeriod
{
    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $creditsUsed,
        public readonly int $creditsLimit,
        public readonly int $creditsRemaining,
        public readonly int $requestsMade
    ) {
    }

    /**
     * Create UsagePeriod from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['start_date'],
            $data['end_date'],
            $data['credits_used'],
            $data['credits_limit'],
            $data['credits_remaining'],
            $data['requests_made']
        );
    }
}
