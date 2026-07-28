<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Strategy for one custom field `type` (spec 0021 — FIELD TYPE STRATEGY,
 * AC-003/AC-004). One implementation per type owns everything the generic
 * decorators (CustomFieldAwareAuthorization / CustomFieldAwareTableDefinition)
 * and the write pipeline (CustomFieldValidator / HasCustomFields) need to stay
 * type-agnostic: storage shape, Laravel validation rules, normalization on
 * write, resolution on read, the AG Grid column/filter shape, and the
 * FieldDescriptor fragment exposed to the frontend meta endpoint.
 *
 * New type = one new class implementing this interface + one config line in
 * config/custom-fields.php (OCP) — no change to any decorator, controller or
 * the write pipeline.
 */
interface FieldTypeHandler
{
    /**
     * The `type` string this handler resolves for (config/custom-fields.php key).
     */
    public function key(): string;

    /**
     * The native PHP/JSON representation a normalized value takes once
     * persisted in `custom_field_values.values`. One of: string, integer,
     * decimal, boolean, json.
     */
    public function storageType(): string;

    /**
     * Laravel validation rules for the field's value, derived from the
     * definition's `config`/`validation`/`relation_target`. Array-valued
     * fields (enum multiselect, relation many) fold their per-element rules
     * into the SAME array via Illuminate\Validation\Rule::forEach, so the
     * caller can attach the whole array to a single `custom_fields.<key>` key
     * without knowing whether the field is single- or multi-valued.
     *
     * @return array<int, mixed>
     */
    public function validationRules(CustomFieldDefinition $definition): array;

    /**
     * Transform an incoming (already-validated) value into its persisted
     * shape (e.g. apply a text transform, cast to int/float/bool, coerce a
     * relation value to int/array-of-int). Null passes through untouched.
     */
    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed;

    /**
     * Transform a persisted value back into the shape returned to the API
     * (`data.custom_fields.<key>`). Identity for most MVP types; a hook for a
     * future type that needs read-side reshaping.
     */
    public function resolveForRead(mixed $stored, CustomFieldDefinition $definition): mixed;

    /**
     * The AG Grid column `type` for this field (drives the frontend cell
     * renderer choice). One of: text, number, boolean, enum.
     */
    public function columnType(): string;

    /**
     * The AG Grid filter widget `filterType` for this field. One of: text,
     * number, boolean, set.
     */
    public function filterType(): string;

    /**
     * Apply a whitelisted SSRM filter payload (identical shape to
     * App\Services\Table\FilterApplier) against `$column-><jsonKey>`. Bound
     * parameters only — BOTH `$column` (the base JSON column, e.g.
     * `custom_field_values.values` or `opportunities.attribute_values`) and
     * `$jsonKey` are always resolved server-side by the caller (an
     * allow-listed column/definition key), never raw request input
     * (backend.md §8 / security.md §8: whereRaw/orderByRaw on input is a SQL
     * injection sink).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $column, string $jsonKey, array $filter): void;

    /**
     * Apply ORDER BY on `$column-><jsonKey>`.
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $column, string $jsonKey, string $direction): void;

    /**
     * Distinct values under `$column-><jsonKey>` across the given
     * (already entity/domain-scoped) query, capped and flattened whether the
     * field is single- or multi-valued. Powers the Excel-like set filter's
     * `/values` endpoint.
     *
     * @param  Builder<Model>  $query
     * @return array<int, scalar>
     */
    public function distinctValues(Builder $query, string $column, string $jsonKey): array;

    /**
     * The type-specific fragment of the FieldDescriptor emitted by
     * `GET /meta/{resource}` for a `source:'custom'` field (label/key/group/
     * tab/sort_order/mandatory are added by the caller from the definition's
     * own columns). Always carries `type`+`config`; `options` only for enum,
     * `relation` only for relation.
     *
     * @return array<string, mixed>
     */
    public function toMeta(CustomFieldDefinition $definition): array;
}
