<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\Attribute;
use App\Models\Quote;

/**
 * Resolves the `custom_fields` and `quote_attributes` variable categories
 * (spec 0069's frozen catalogue, AC-042/043; spec 0084 re-sourced the second
 * one): the two DYNAMIC categories, one variable per live
 * `CustomFieldDefinition`/`Attribute` rather than a fixed list — split out of
 * VariableResolver to keep it under the file-size soft limit
 * (engineering.md §6).
 *
 * Both read from an already-resolved array accessor (`Quote::custom_fields`,
 * `Quote::attribute_values`), so a KEY absent from that array (spec 0070
 * AC-237: a custom field whose definition was deleted after the layout
 * referenced it) and a key present but null both render identically — an
 * empty string — with no distinction needed: the generation must never fail
 * on a stale reference.
 */
final class DynamicFieldResolver
{
    /**
     * Option labels per enum attribute code (null = not an enum, or no such
     * attribute), memoized for the instance: a layout can reference the same
     * attribute many times in one render.
     *
     * @var array<string, array<string, string>|null>
     */
    private array $enumLabels = [];

    public function customField(string $key, Quote $quote): string
    {
        return self::scalarToString($quote->custom_fields[$key] ?? null);
    }

    /**
     * Spec 0084: reads straight off THIS quote's own `attribute_values` —
     * the dynamic "Informazioni aggiuntive" moved from the Opportunity to the
     * Offerta, one per quote rather than one per opportunity.
     */
    public function quoteAttribute(string $key, Quote $quote): string
    {
        $value = ($quote->attribute_values ?? [])[$key] ?? null;
        $labels = $value === null ? null : $this->enumLabels($key);

        return self::scalarToString($labels === null ? $value : self::toLabels($value, $labels));
    }

    /**
     * @return array<string, string>|null
     */
    private function enumLabels(string $code): ?array
    {
        if (! array_key_exists($code, $this->enumLabels)) {
            $attribute = Attribute::query()->where('code', $code)->where('type', 'enum')->with('options')->first();
            $this->enumLabels[$code] = $attribute?->options->pluck('label', 'value')->all();
        }

        return $this->enumLabels[$code];
    }

    /**
     * A stored option code (or a multiselect list of them) swapped for its
     * label. A code with no option left — deleted after it was saved — prints
     * as itself rather than vanishing from the document.
     *
     * @param  array<string, string>  $labels
     */
    private static function toLabels(mixed $value, array $labels): mixed
    {
        $label = static fn (mixed $code): mixed => is_scalar($code) ? ($labels[(string) $code] ?? $code) : $code;

        return is_array($value) ? array_map($label, $value) : $label($value);
    }

    /**
     * A dynamic field's raw stored value (scalar, bool, or a multiselect
     * array) rendered as display text — never "null", per the rendering
     * contract's masked/missing-value rule.
     */
    private static function scalarToString(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_array($value) && array_key_exists('rows', $value) => self::scalarToString($value['summary'] ?? null),
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value)),
            default => (string) $value,
        };
    }
}
