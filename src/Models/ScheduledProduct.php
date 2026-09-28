<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * One product in a scheduling response.
 *
 * Both PUT /products/scheduled (scheduleProductMonitoring) and GET /products/scheduled
 * (getScheduledProducts) return each entry as `{ ...product fields, schedule, retailer? }`:
 * every field the products endpoint returns, plus the refresh `schedule` and — when the
 * schedule is limited to one retailer — that `retailer` domain.
 *
 * `schedule` is 'hourly', 'daily' or 'weekly'. On the list endpoint it is null when the
 * stored refresh interval has no Data API label (e.g. a 4h or 12h interval set from
 * ShopSavvy Business). `retailer` is null when the product is watched at every retailer.
 *
 * Earlier releases modelled this as `{product_id, identifier, frequency, created_at,
 * last_refreshed}` — keys the API has never sent.
 */
class ScheduledProduct extends ProductDetails
{
    /**
     * @param array<string>|null $images
     * @param array<int, string>|null $categories
     * @param array<string, mixed>|null $attributes
     * @param array<string, mixed>|null $rating
     * @param array<string, mixed>|null $score
     * @param array<int, string>|null $keywords
     * @param array<string, mixed>|null $identifiers
     */
    public function __construct(
        string $title,
        string $shopsavvy,
        ?string $brand = null,
        ?string $category = null,
        ?array $images = null,
        ?string $barcode = null,
        ?string $amazon = null,
        ?string $model = null,
        ?string $mpn = null,
        ?string $color = null,
        ?string $titleShort = null,
        ?string $slug = null,
        ?string $description = null,
        ?array $categories = null,
        ?array $attributes = null,
        ?array $rating = null,
        ?array $score = null,
        ?array $keywords = null,
        ?array $identifiers = null,
        public readonly ?string $schedule = null,
        public readonly ?string $retailer = null
    ) {
        parent::__construct(
            $title,
            $shopsavvy,
            $brand,
            $category,
            $images,
            $barcode,
            $amazon,
            $model,
            $mpn,
            $color,
            $titleShort,
            $slug,
            $description,
            $categories,
            $attributes,
            $rating,
            $score,
            $keywords,
            $identifiers
        );
    }

    /**
     * Create ScheduledProduct from one element of the response's `data` array
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        // Parse the product fields exactly as ProductDetails does (including the
        // numeric-barcode normalization), then attach schedule and retailer.
        $product = ProductDetails::fromArray($data);

        return new self(
            $product->title,
            $product->shopsavvy,
            $product->brand,
            $product->category,
            $product->images,
            $product->barcode,
            $product->amazon,
            $product->model,
            $product->mpn,
            $product->color,
            $product->titleShort,
            $product->slug,
            $product->description,
            $product->categories,
            $product->attributes,
            $product->rating,
            $product->score,
            $product->keywords,
            $product->identifiers,
            $data['schedule'] ?? null,
            $data['retailer'] ?? null
        );
    }

    /**
     * Parse a response body's `data` list
     *
     * @param array<string, mixed> $body The decoded response body
     * @return array<ScheduledProduct>
     */
    public static function listFromResponse(array $body): array
    {
        if (!isset($body['data']) || !is_array($body['data'])) {
            return [];
        }

        return array_values(array_map(
            fn(array $product) => self::fromArray($product),
            $body['data']
        ));
    }

    public function isHourly(): bool
    {
        return $this->schedule === 'hourly';
    }

    public function isDaily(): bool
    {
        return $this->schedule === 'daily';
    }

    public function isWeekly(): bool
    {
        return $this->schedule === 'weekly';
    }

    /**
     * @deprecated Use schedule instead
     */
    public function getFrequency(): ?string
    {
        return $this->schedule;
    }
}
