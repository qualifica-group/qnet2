<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\ProductCategory;
use App\WorkOrders\WorkOrderAttributeLock;
use Integrations\Formalab\Support\FormalabField;

/**
 * The work order fields Formalab writes are read-only for the user (spec
 * 0423 D-13): every attribute mapped by a linked category, except the one
 * mapped on the course name, which the user fills to find the edition.
 */
final class MappedFieldLock implements WorkOrderAttributeLock
{
    public function __construct(private readonly CategoryFieldMapResolver $maps) {}

    public function lockedCodes(array $categoryIds): array
    {
        $locked = [];
        $keys = [];

        foreach (ProductCategory::query()->whereIn('id', $categoryIds)->get() as $category) {
            $resolved = $this->maps->forCategory($category);

            if (! $resolved['enabled']) {
                continue;
            }

            foreach ($resolved['field_map'] as $field => $code) {
                if ($field === FormalabField::Label->value) {
                    $keys[] = $code;
                } else {
                    $locked[] = $code;
                }
            }
        }

        return array_values(array_unique(array_diff($locked, $keys)));
    }
}
