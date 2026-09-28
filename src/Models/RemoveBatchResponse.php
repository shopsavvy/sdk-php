<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Response from batch removal
 */
class RemoveBatchResponse
{
    public function __construct(
        public readonly string $identifier,
        public readonly bool $removed
    ) {
    }

    /**
     * Create RemoveBatchResponse from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['identifier'],
            $data['removed']
        );
    }
}
