<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * One product in a getPriceHistory() response.
 *
 * GET /products/offers/history returns one entry PER PRODUCT: every product field the
 * products endpoint returns (title, barcode, amazon, brand, images, ...) plus an `offers`
 * list, and each offer carries its own `history` array of price points. Earlier releases
 * modelled that response as a flat list of offers (OfferWithHistory at the top level),
 * which is not the shape the API sends.
 */
class ProductWithPriceHistory extends ProductDetails
{
    /**
     * @param array<string>|null $images
     * @param array<int, string>|null $categories
     * @param array<string, mixed>|null $attributes
     * @param array<string, mixed>|null $rating
     * @param array<string, mixed>|null $score
     * @param array<int, string>|null $keywords
     * @param array<string, mixed>|null $identifiers
     * @param array<OfferWithHistory> $offers Each offer at each retailer, with its price history
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
        public readonly array $offers = []
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
     * Create ProductWithPriceHistory from one element of the response's `data` array
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        // Parse the product fields exactly as ProductDetails does (including the
        // numeric-barcode normalization), then attach the offers.
        $product = ProductDetails::fromArray($data);

        $offers = [];
        if (isset($data['offers']) && is_array($data['offers'])) {
            $offers = array_map(
                fn(array $offer) => OfferWithHistory::fromArray($offer),
                $data['offers']
            );
        }

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
            $offers
        );
    }
}
