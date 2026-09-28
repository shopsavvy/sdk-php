<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Request model for removing scheduled products
 */
class RemoveRequest
{
    public function __construct(
        public readonly string $identifier
    ) {
    }

    /**
     * Convert to array for JSON encoding
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier];
    }
}
