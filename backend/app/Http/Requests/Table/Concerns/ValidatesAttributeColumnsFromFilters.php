<?php

declare(strict_types=1);

namespace App\Http\Requests\Table\Concerns;

/**
 * The OPTIONAL `attribute_columns_from_filters` flag of the table endpoints
 * that return a config (spec 0374, D-2): only the pages whose `attr.*` columns
 * follow the user's category filter send it. Like the category scopes it never
 * touches what is persisted.
 */
trait ValidatesAttributeColumnsFromFilters
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function attributeColumnsFromFiltersRules(): array
    {
        return ['attribute_columns_from_filters' => ['sometimes', 'boolean']];
    }

    public function attributeColumnsFromFilters(): bool
    {
        return (bool) $this->validated('attribute_columns_from_filters', false);
    }
}
