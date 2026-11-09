<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * A shelf price a physical store charged on a given day, read from a shopper's scanned
 * receipt. `source` is always "user-collected". It says what the store sold the product for
 * that day, not that it is in stock now; nothing about the shopper is included.
 */
class InStorePrice
{
    /**
     * @param float $price Unit shelf price paid (the median when several receipts from the store agree)
     * @param string|null $observedOn YYYY-MM-DD date of the most recent receipt behind the price
     */
    public function __construct(
        public readonly string $source,
        public readonly ?string $retailer,
        public readonly ?string $address,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly float $price,
        public readonly string $currency,
        public readonly ?string $observedOn = null,
        public readonly ?string $timestamp = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $store = is_array($data['store'] ?? null) ? $data['store'] : [];
        return new self(
            (string) ($data['source'] ?? 'user-collected'),
            $data['retailer'] ?? null,
            $store['address'] ?? null,
            isset($store['latitude']) ? (float) $store['latitude'] : null,
            isset($store['longitude']) ? (float) $store['longitude'] : null,
            (float) $data['price'],
            (string) $data['currency'],
            $data['observed_on'] ?? null,
            $data['timestamp'] ?? null
        );
    }
}
