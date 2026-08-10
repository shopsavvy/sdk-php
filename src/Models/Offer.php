<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Product offer model
 */
class Offer
{
    /**
     * @param array<PriceHistoryEntry>|null $history
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $retailer = null,
        public readonly ?float $price = null,
        public readonly ?string $currency = null,
        public readonly ?string $availability = null,
        public readonly ?string $condition = null,
        public readonly ?string $url = null,
        public readonly ?string $seller = null,
        public readonly ?string $timestamp = null,
        public readonly ?array $history = null
    ) {
    }

    /**
     * Create Offer from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $history = null;
        if (isset($data['history']) && is_array($data['history'])) {
            $history = array_map(
                fn(array $point) => PriceHistoryEntry::fromArray($point),
                $data['history']
            );
        }

        return new self(
            $data['id'],
            $data['retailer'] ?? null,
            $data['price'] ?? null,
            $data['currency'] ?? null,
            $data['availability'] ?? null,
            $data['condition'] ?? null,
            $data['URL'] ?? null,  // API returns URL (capital)
            $data['seller'] ?? null,
            $data['timestamp'] ?? null,
            $history
        );
    }

    // Backward-compatible aliases

    /**
     * @deprecated Use id instead
     */
    public function getOfferId(): string
    {
        return $this->id;
    }

    /**
     * @deprecated Use url instead
     */
    public function getOfferUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @deprecated Use timestamp instead
     */
    public function getLastUpdated(): ?string
    {
        return $this->timestamp;
    }
}

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

/**
 * Offer returned by getPriceHistory(), i.e. one carrying its `history` array.
 *
 * `fromArray` used to read a `price_history` key. The API has never sent one — history has
 * always arrived under `history` — so the `isset()` guard was always false, `array_map` never
 * ran, and `$history` stayed at its `[]` default for every product, retailer and date range.
 * No exception, no warning: getPriceHistory() succeeded and always reported zero price points
 * (ShopSavvy prospector-audit s28-t2-3).
 */
class OfferWithHistory
{
    /**
     * @param array<PriceHistoryEntry> $history
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $retailer = null,
        public readonly ?float $price = null,
        public readonly ?string $currency = null,
        public readonly ?string $availability = null,
        public readonly ?string $condition = null,
        public readonly ?string $url = null,
        public readonly ?string $seller = null,
        public readonly ?string $timestamp = null,
        public readonly array $history = []
    ) {
    }

    /**
     * Create OfferWithHistory from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $history = [];
        if (isset($data['history']) && is_array($data['history'])) {
            $history = array_map(
                fn(array $point) => PriceHistoryEntry::fromArray($point),
                $data['history']
            );
        }

        return new self(
            $data['id'],
            $data['retailer'] ?? null,
            $data['price'] ?? null,
            $data['currency'] ?? null,
            $data['availability'] ?? null,
            $data['condition'] ?? null,
            $data['URL'] ?? null,  // API returns URL (capital)
            $data['seller'] ?? null,
            $data['timestamp'] ?? null,
            $history
        );
    }
}
