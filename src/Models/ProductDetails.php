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
     * @param array<int, string>|null $categories
     * @param array<string, mixed>|null $attributes
     * @param array<string, mixed>|null $rating {value, count}
     * @param array<string, mixed>|null $score
     * @param array<int, string>|null $keywords
     * @param array<string, mixed>|null $identifiers
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
        /**
         * Expert quality scores on a 0-1 scale (multiply by 10 or 100 for
         * display): "overall", "customer", "professional", plus an "aspects"
         * array keyed by free-form aspect names from the product's
         * professional reviews.
         */
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
            self::stringOrNull($data['barcode'] ?? null),
            $data['amazon'] ?? null,
            self::stringOrNull($data['model'] ?? null),
            self::stringOrNull($data['mpn'] ?? null),
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
