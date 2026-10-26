<?php

namespace App\Exports;

use App\Enums\Concerns\HasMeta;
use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;
use UnitEnum;

/**
 * Formats one mapRow() value for the export file according to the column's
 * declared `type` (spec 0014), so the file mirrors what the grid shows:
 * datetime → the configured format; boolean → a localized yes/no; tags/array
 * → joined with '; '; enum → its label; null → ''; anything else → its
 * string form. Not a
 * pixel-perfect match of the frontend cell renderers (documented trade-off) —
 * the backend-formatted value is the single source of truth.
 */
class ExportValueFormatter
{
    public function format(mixed $value, string $type): string
    {
        return match ($type) {
            'datetime' => $this->formatDatetime($value),
            'boolean' => $this->formatBoolean($value),
            'tags', 'array' => $this->formatArray($value),
            default => $this->formatScalar($value),
        };
    }

    private function formatDatetime(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $carbon = match (true) {
            $value instanceof DateTimeInterface => Carbon::instance($value),
            is_string($value) && $value !== '' => Carbon::parse($value),
            default => null,
        };

        return $carbon?->format((string) config('exports.datetime_format')) ?? $this->formatScalar($value);
    }

    private function formatBoolean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return $value ? __('exports.boolean_true') : __('exports.boolean_false');
    }

    /**
     * Each array item is stringified individually (a related-entity summary
     * array uses its `name` key when present) before being joined, so a
     * `tags` column of scalars OR of `{id,name,...}` summaries both export
     * cleanly.
     */
    private function formatArray(mixed $value): string
    {
        if (! is_array($value)) {
            return $this->formatScalar($value);
        }

        return implode('; ', array_map(
            fn (mixed $item): string => is_array($item)
                ? (string) ($item['name'] ?? json_encode($item))
                : $this->formatScalar($item),
            $value,
        ));
    }

    /**
     * A single related-entity summary (a person column's `{id, name, ...}`)
     * exports as its `name`, the same rule formatArray() applies per item.
     */
    private function formatScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // A list of summaries (a team, the product-line pairs) exports as
        // their names, exactly like a `tags` column.
        if (is_array($value) && array_is_list($value)) {
            return $this->formatArray($value);
        }

        if (is_array($value)) {
            return (string) ($value['name'] ?? json_encode($value));
        }

        if ($value instanceof UnitEnum) {
            return $this->formatEnum($value);
        }

        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    /**
     * An enum cast left in a mapRow value (e.g. the products `product_type`)
     * exports as the label the grid badge shows when the enum declares one,
     * otherwise as the value the grid receives in JSON.
     */
    private function formatEnum(UnitEnum $value): string
    {
        if (in_array(HasMeta::class, class_uses($value), true)) {
            return $value->label();
        }

        return $value instanceof BackedEnum ? (string) $value->value : $value->name;
    }
}
