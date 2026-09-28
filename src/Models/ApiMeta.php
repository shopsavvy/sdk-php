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
