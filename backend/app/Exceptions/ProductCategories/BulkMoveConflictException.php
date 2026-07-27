<?php

namespace App\Exceptions\ProductCategories;

use RuntimeException;

/**
 * A bulk move (spec 0063) rejected before any write: the whole batch is
 * refused as soon as one reason yields conflicts (decision D-3, all-or-
 * nothing), so no category is ever left half-moved.
 *
 * Carries the machine-readable `reason` and the full list of offending rows
 * so the controller can surface them in one 422 and the frontend can name
 * them; the exception itself never decides the HTTP status.
 */
final class BulkMoveConflictException extends RuntimeException
{
    /**
     * @param  array<int, array{id: int, name: string, detail: string}>  $conflicts
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $conflicts,
        string $message,
    ) {
        parent::__construct($message);
    }
}
