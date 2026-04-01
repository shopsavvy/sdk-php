<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Product details model
 */
class ProductDetails
{
    /**
     * @param array<string>|null $images
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
        public readonly ?string $titleShort = null,
        public readonly ?string $slug = null,
        public readonly ?string $description = null,
        public readonly ?array $categories = null,
        public readonly ?array $attributes = null,
        public readonly ?array $rating = null,
        public readonly ?array $score = null,
        public readonly ?array $keywords = null,
        public readonly ?array $identifiers = null
    ) {
    }

    /**
     * Create ProductDetails from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['title'],
            $data['shopsavvy'],
            $data['brand'] ?? null,
            $data['category'] ?? null,
            $data['images'] ?? null,
            $data['barcode'] ?? null,
            $data['amazon'] ?? null,
            $data['model'] ?? null,
            $data['mpn'] ?? null,
            $data['color'] ?? null,
            $data['title_short'] ?? null,
            $data['slug'] ?? null,
            $data['description'] ?? null,
            $data['categories'] ?? null,
            $data['attributes'] ?? null,
            $data['rating'] ?? null,
            $data['score'] ?? null,
            $data['keywords'] ?? null,
            $data['identifiers'] ?? null
        );
    }

    // Backward-compatible aliases

    /**
     * @deprecated Use title instead
     */
    public function getName(): string
    {
        return $this->title;
    }

    /**
     * @deprecated Use shopsavvy instead
     */
    public function getProductId(): string
    {
        return $this->shopsavvy;
    }

    /**
     * @deprecated Use amazon instead
     */
    public function getAsin(): ?string
    {
        return $this->amazon;
    }

    /**
     * @deprecated Use images[0] instead
     */
    public function getImageUrl(): ?string
    {
        return $this->images[0] ?? null;
    }
}

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
            $data['barcode'] ?? null,
            $data['amazon'] ?? null,
            $data['model'] ?? null,
            $data['mpn'] ?? null,
            $data['color'] ?? null,
            $offers
        );
    }
}
