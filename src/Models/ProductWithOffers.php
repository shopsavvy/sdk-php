<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Product with nested offers (returned by offers endpoint)
 */
class ProductWithOffers
{
    /**
     * @param array<string>|null $images
     * @param array<Offer> $offers
     */
    public function __construct(
        public readonly string $title,
        public readonly string $shopsavvy,
        public readonly ?string $brand = null,
        public readonly ?string $category = null,
        public readonly ?array $images = null,
        public readonly ?string $barcode = null,
        public readonly ?string $amazon = null,
        public readonly ?string $model = null,
        public readonly ?string $mpn = null,
        public readonly ?string $color = null,
        public readonly array $offers = []
    ) {
    }

    /**
     * Create ProductWithOffers from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $offers = [];
        if (isset($data['offers']) && is_array($data['offers'])) {
            $offers = array_map(
                fn(array $offer) => Offer::fromArray($offer),
                $data['offers']
            );
        }

        return new self(
            $data['title'],
            $data['shopsavvy'],
            $data['brand'] ?? null,
            $data['category'] ?? null,
            $data['images'] ?? null,
            self::stringOrNull($data['barcode'] ?? null),
            $data['amazon'] ?? null,
            self::stringOrNull($data['model'] ?? null),
            self::stringOrNull($data['mpn'] ?? null),
            $data['color'] ?? null,
            $offers
        );
    }

    /**
     * The API sends `barcode` as a JSON number (e.g. 611247373064), and
     * `model`/`mpn` can be numeric too. Under strict_types passing an int to
     * a ?string parameter is a TypeError, so these are normalized to strings.
     */
    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
