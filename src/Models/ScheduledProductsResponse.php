<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Response from getScheduledProducts() (GET /products/scheduled):
 * `{ success, data: [ product + schedule? + retailer? ], meta }`.
 *
 * `data` holds every product scheduled under this API key, oldest first. An entry's
 * `schedule` is null when its interval has no Data API label, and `retailer` is null
 * when it is watched at every retailer.
 */
class ScheduledProductsResponse
{
    /**
     * @param array<ScheduledProduct> $data
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly ?ApiMeta $meta = null,
        public readonly ?string $message = null
    ) {
    }

    /**
     * Create ScheduledProductsResponse from the decoded response body
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['success'] ?? true,
            ScheduledProduct::listFromResponse($data),
            isset($data['meta']) ? ApiMeta::fromArray($data['meta']) : null,
            $data['message'] ?? null
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
