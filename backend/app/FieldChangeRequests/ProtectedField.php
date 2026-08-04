<?php

declare(strict_types=1);

namespace App\FieldChangeRequests;

/**
 * One entry of `config/field-change-requests.php` (spec 0078, D-1):
 * a single protected field on a single resource, and everything the rest of
 * the system needs to enforce/expose it — the dedicated permission name, the
 * TableDefinition column it maps to (D-7), and the i18n labels used to
 * render a `FieldChangeRequestResource`.
 */
final readonly class ProtectedField
{
    public function __construct(
        public string $resource,
        public string $field,
        public string $ability,
        public string $column,
        public string $fieldLabel,
        public string $resourceLabel,
        public string $recordPath,
    ) {}

    /**
     * The permission name generated for this field, e.g.
     * "request-management.updateSource" — what `permissions:sync` creates and
     * what an actor must hold to write the field directly on any channel.
     */
    public function permission(): string
    {
        return "{$this->resource}.{$this->ability}";
    }
}
