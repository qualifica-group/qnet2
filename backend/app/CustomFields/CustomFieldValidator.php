<?php

declare(strict_types=1);

namespace App\CustomFields;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldPermission;
use App\CustomFields\Types\ProvidesNestedValidationRules;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator as ValidationContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Validates the write pipeline's pending `custom_fields` payload (spec 0021
 * — INNESTO WRITE, AC-011/AC-012), run from
 * App\Models\Concerns\HasCustomFields' `saving` observer so a failure aborts
 * BEFORE anything is persisted.
 *
 * Two independent gates against the flat `[key => value]` map (never the
 * namespaced `custom.<key>` form — that prefix is a meta/authorization
 * concern, see CustomFieldProvider::namespacedKey()):
 *
 *  1. Value-level rules (AC-011): built per submitted-or-defined key from the
 *     definition's own FieldTypeHandler::validationRules() — required/type/
 *     enum-options/relation-exists/regex/min/max all live in the handler, not
 *     here (OCP — this class stays type-agnostic).
 *  2. Field-permission parity with EnforcesFieldPermissions (AC-012): a
 *     submitted key mapped to a NON-editable `custom.<key>` for the current
 *     actor+model, whose value actually CHANGED versus what is persisted, is
 *     rejected the same way a non-editable native field is. Requires an
 *     actor — skipped (not bypassed-as-editable, simply not evaluated) when
 *     there is none, e.g. a console-created model.
 *
 * Both surface under `custom_fields.<key>` in a thrown ValidationException —
 * Laravel renders that as a 422 with that exact error key.
 */
class CustomFieldValidator
{
    public function __construct(
        private readonly CustomFieldEntityRegistry $entityRegistry,
        private readonly CustomFieldProvider $provider,
        private readonly FieldTypeRegistry $typeRegistry,
        private readonly AuthorizationRegistry $authorizationRegistry,
    ) {}

    /**
     * Peeks (non-destructively) at the pending bag values and validates them
     * against $model, using the currently authenticated actor when there is
     * one. No-op when there is nothing pending. Without an authenticated
     * actor (e.g. a console-created model, which never goes through
     * CaptureCustomFields anyway) the value-level rules (AC-011) still run in
     * full — only the actor-dependent permission-parity gate (AC-012) is
     * skipped, see validate().
     */
    public function validateFromRequestBag(Model $model): void
    {
        $bag = app(CustomFieldRequestBag::class);

        if (! $bag->has()) {
            return;
        }

        $this->validate($model, $bag->values(), auth()->user());
    }

    /**
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    public function validate(Model $model, array $values, ?User $actor): void
    {
        $entityType = $this->entityRegistry->entityTypeForModel($model);

        if ($entityType === null || $values === []) {
            return;
        }

        $definitions = $this->provider->definitionsFor($entityType)->keyBy('key');

        $validator = ValidatorFacade::make(
            ['custom_fields' => $values],
            $this->rules($definitions, $model->exists),
        );

        if ($actor !== null) {
            $validator->after(function (ValidationContract $validator) use ($definitions, $entityType, $model, $values, $actor): void {
                $this->guardFieldPermissions($validator, $definitions, $entityType, $model, $values, $actor);
            });
        }

        $validator->validate();
    }

    /**
     * Value-level rules per definition. On update ($isUpdate), a `sometimes`
     * guard is prepended so a `required` custom field already persisted but
     * NOT resubmitted in this partial payload (AC-012 merge semantics) is not
     * force-failed; on create, `required` (already baked into the handler's
     * own rules) applies in full.
     *
     * @param  Collection<string, CustomFieldDefinition>  $definitions
     * @return array<string, array<int, mixed>>
     */
    private function rules(Collection $definitions, bool $isUpdate): array
    {
        $rules = [];

        foreach ($definitions as $key => $definition) {
            $handler = $this->typeRegistry->resolve($definition->type);
            $handlerRules = $handler->validationRules($definition);
            $rules["custom_fields.{$key}"] = $isUpdate ? ['sometimes', ...$handlerRules] : $handlerRules;

            if ($handler instanceof ProvidesNestedValidationRules) {
                foreach ($handler->nestedValidationRules($definition) as $suffix => $nestedRules) {
                    $rules["custom_fields.{$key}.{$suffix}"] = $nestedRules;
                }
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @param  Collection<string, CustomFieldDefinition>  $definitions
     */
    private function guardFieldPermissions(
        ValidationContract $validator,
        Collection $definitions,
        string $entityType,
        Model $model,
        array $submitted,
        User $actor,
    ): void {
        $resource = $this->entityRegistry->resourceFor($entityType);

        if ($resource === null) {
            return;
        }

        $authorizationModel = $model->exists ? $model : null;
        $permissions = $this->authorizationRegistry->resolve($resource)->fieldPermissions($actor, $authorizationModel);
        $current = $this->currentValues($entityType, $model);

        foreach ($permissions as $field => $permission) {
            /** @var FieldPermission $permission */
            if (! str_starts_with($field, CustomFieldProvider::KEY_PREFIX)) {
                continue;
            }

            $key = substr($field, strlen(CustomFieldProvider::KEY_PREFIX));

            if ($permission->editable || ! array_key_exists($key, $submitted)) {
                continue;
            }

            if ($this->changed($definitions->get($key), $submitted[$key], $current[$key] ?? null)) {
                $validator->errors()->add("custom_fields.{$key}", 'field not editable');
            }
        }
    }

    /**
     * The currently persisted values map for $model, or an empty map on
     * create (nothing persisted yet — mirrors EnforcesFieldPermissions'
     * currentFieldValue() null-on-create behaviour).
     *
     * @return array<string, mixed>
     */
    private function currentValues(string $entityType, Model $model): array
    {
        if (! $model->exists) {
            return [];
        }

        $row = CustomFieldValue::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $model->getKey())
            ->first();

        return $row?->values ?? [];
    }

    /**
     * Compares submitted vs persisted AFTER the handler's normalizeForStore,
     * so a structured value re-sent as read (server-computed keys, ids) is not
     * seen as modified. Defensive: a value the handler cannot normalize is
     * treated as changed.
     */
    private function changed(?CustomFieldDefinition $definition, mixed $submitted, mixed $current): bool
    {
        if ($definition !== null) {
            $handler = $this->typeRegistry->resolve($definition->type);

            try {
                $submitted = $handler->normalizeForStore($submitted, $definition);
                $current = $handler->normalizeForStore($current, $definition);
            } catch (Throwable) {
                return true;
            }
        }

        return $this->normalize($submitted) !== $this->normalize($current);
    }

    /**
     * Scalar/array-shape-tolerant normalization: null/''/[] all compare as
     * "nothing", and scalar lists (multiselect/relation-many) compare by
     * content rather than submission order.
     */
    private function normalize(mixed $value): mixed
    {
        if (is_array($value) && ! array_is_list($value)) {
            return $value === [] ? null : array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        if (is_array($value)) {
            $normalized = array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
            sort($normalized);

            return $normalized === [] ? null : $normalized;
        }

        return $value === null || $value === '' ? null : $value;
    }
}
