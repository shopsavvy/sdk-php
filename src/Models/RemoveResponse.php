<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Response from removeProductFromSchedule() (DELETE /products/scheduled):
 * `{ success, message, meta }`.
 *
 * The server sends no `data`. Earlier releases modelled this as `{removed}`, which the
 * API never sends.
 */
class RemoveResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly ?ApiMeta $meta = null
    ) {
    }

    /**
     * Create RemoveResponse from the decoded response body
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['success'] ?? true,
            $data['message'] ?? null,
            isset($data['meta']) ? ApiMeta::fromArray($data['meta']) : null
        );
    }
}
