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
 * Historical price point
 */
class PriceHistoryEntry
{
    public function __construct(
        public readonly string $date,
        public readonly float $price,
        public readonly string $availability
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
            $data['date'],
            $data['price'],
            $data['availability']
        );
    }
}

/**
 * Offer with price history
 */
class OfferWithHistory
{
    /**
     * @param array<PriceHistoryEntry> $priceHistory
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
        public readonly array $priceHistory = []
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
        $priceHistory = [];
        if (isset($data['price_history']) && is_array($data['price_history'])) {
            $priceHistory = array_map(
                fn(array $point) => PriceHistoryEntry::fromArray($point),
                $data['price_history']
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
            $priceHistory
        );
    }
}
