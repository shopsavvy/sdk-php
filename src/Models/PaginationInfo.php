<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Pagination info for search results
 */
class PaginationInfo
{
    public function __construct(
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
        public readonly int $returned
    ) {
    }

    /**
     * Create PaginationInfo from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['total'],
            $data['limit'],
            $data['offset'],
            $data['returned']
        );
    }
}
