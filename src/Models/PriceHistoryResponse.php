<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Response from getPriceHistory():
 * `{ success, data: [ product + offers[ offer + history[] ] ], meta }`.
 */
class PriceHistoryResponse
{
    /**
     * @param array<ProductWithPriceHistory> $data One entry per product found
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly ?ApiMeta $meta = null,
        public readonly ?string $message = null
    ) {
    }

    /**
     * Create PriceHistoryResponse from the decoded response body
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $products = [];
        if (isset($data['data']) && is_array($data['data'])) {
            $products = array_map(
                fn(array $product) => ProductWithPriceHistory::fromArray($product),
                $data['data']
            );
        }

        return new self(
            $data['success'] ?? true,
            $products,
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
