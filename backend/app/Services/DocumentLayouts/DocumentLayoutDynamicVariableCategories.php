<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\CustomFields\CustomFieldProvider;
use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\CustomFieldDefinition;
use Illuminate\Support\Facades\DB;

/**
 * The two DYNAMIC categories of App\Services\DocumentLayouts\DocumentLayoutVariableCatalog
 * (spec 0069, AC-042/043), split out to keep that class under the file-size
 * soft limit (engineering.md §6): `custom_fields` (one variable per active
 * CustomFieldDefinition of the given entity_type) and
 * `opportunity_attributes` (one variable per catalogue Attribute assigned to
 * at least one product category's Opportunity section, spec 0061's
 * AttributeContext::Opportunity — module-independent since attributes are
 * never scoped to a document-layouts module).
 *
 * Neither category carries PII (D-6 only ever masks `client.*`, see
 * DocumentLayoutVariableCatalog's class docblock), so no actor/field-permission
 * argument is needed here.
 */
final class DocumentLayoutDynamicVariableCategories
{
    private const string TYPE_STRING = 'string';

    private const string TYPE_NUMBER = 'number';

    private const string TYPE_DATE = 'date';

    public function __construct(private readonly CustomFieldProvider $customFieldProvider) {}

    /**
     * @return array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}
     */
    public function customFields(string $entityType): array
    {
        $variables = $this->customFieldProvider->definitionsFor($entityType)
            ->map(fn (CustomFieldDefinition $definition): array => $this->entry(
                "custom_fields.{$definition->key}",
                $definition->label,
                $definition->type,
            ))
            ->values()
            ->all();

        return ['key' => 'custom_fields', 'label' => __('document_layouts.variables.categories.custom_fields'), 'variables' => $variables];
    }

    /**
     * @return array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}
     */
    public function opportunityAttributes(): array
    {
        // A plain whereIn against the pivot table, not whereHas()+wherePivot():
        // the whereHas() constraint closure receives a base Eloquent Builder
        // for the related model, which does not expose wherePivot() (that
        // method lives on the BelongsToMany relation object itself) — this
        // reads the pivot table directly instead of relying on that.
        $attributeIds = DB::table('attribute_category')
            ->where('context', AttributeContext::Opportunity->value)
            ->distinct()
            ->pluck('attribute_id');

        $variables = Attribute::query()
            ->whereIn('id', $attributeIds)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Attribute $attribute): array => $this->entry(
                "opportunity_attributes.{$attribute->code}",
                $attribute->name,
                $attribute->type,
            ))
            ->values()
            ->all();

        return ['key' => 'opportunity_attributes', 'label' => __('document_layouts.variables.categories.opportunity_attributes'), 'variables' => $variables];
    }

    /**
     * @return array{variable: string, label: string, type: string, example: string}
     */
    private function entry(string $token, string $label, string $fieldType): array
    {
        $type = $this->fieldTypeToVariableType($fieldType);

        return [
            'variable' => "{{$token}}",
            'label' => $label,
            'type' => $type,
            'example' => $this->exampleFor($type),
        ];
    }

    /**
     * Maps a CustomFieldDefinition/Attribute `type` (both draw from
     * App\CustomFields\FieldTypeRegistry's keys, per Attribute's own
     * docblock) to this contract's 4-value `type` enum. An unmapped/future
     * type defaults to `string` rather than throwing: the catalogue must
     * stay usable even if FieldTypeRegistry gains a type this mapping has
     * not been updated for yet. No `currency` case: neither custom fields
     * nor attributes declare a currency-specific type today.
     */
    private function fieldTypeToVariableType(string $fieldType): string
    {
        return match ($fieldType) {
            'integer', 'decimal' => self::TYPE_NUMBER,
            'date', 'datetime', 'time' => self::TYPE_DATE,
            default => self::TYPE_STRING,
        };
    }

    private function exampleFor(string $type): string
    {
        return match ($type) {
            self::TYPE_NUMBER => '123',
            self::TYPE_DATE => '2026-07-30',
            default => 'Example value',
        };
    }
}
