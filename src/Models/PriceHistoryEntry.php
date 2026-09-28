<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Historical price point.
 *
 * The timestamp field is `timestamp`, matching the parent Offer's own `timestamp` and the
 * real wire shape ({availability, price, timestamp}). Every SDK in the fleet read it from a
 * `date` key — one the API has never sent — until 2026-08-10
 * (ShopSavvy prospector-audit s28-t2-2 / s28-t2-3).
 */
class PriceHistoryEntry
{
    /**
     * @param string|null $currency ISO 4217 code $price is denominated in. Null on an archived
     *   point with no recorded currency - never assume a missing value means USD
     *   (ShopSavvy prospector-audit d5-t3-1).
     */
    public function __construct(
        public readonly string $timestamp,
        public readonly float $price,
        public readonly ?string $currency = null,
        public readonly ?string $availability = null
    ) {
    }

    /**
     * Create PriceHistoryEntry from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['timestamp'],
            $data['price'],
            $data['currency'] ?? null,
            $data['availability'] ?? null
        );
    }
}
