<?php

declare(strict_types=1);

namespace Integrations\Formalab\Tables\Concerns;

/**
 * The column shape of the Formalab tables; labels are `LABELS.<id>` i18n
 * keys of the using definition.
 */
trait BuildsColumns
{
    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private function column(string $id, string $type, bool $visible = true, bool $sortable = false, ?string $filter = null, ?array $options = null, bool $searchable = false): array
    {
        $column = [
            'id' => $id,
            'label' => self::LABELS.".{$id}",
            'type' => $type,
            'visible' => $visible,
            'sortable' => $sortable,
            'filterable' => $filter !== null,
            'filterType' => $filter,
        ];

        if ($options !== null) {
            $column['options'] = $options;
        }

        if ($searchable) {
            $column['searchable'] = true;
        }

        return $column;
    }
}
