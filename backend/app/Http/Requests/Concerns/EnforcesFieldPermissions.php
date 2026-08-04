<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldPermission;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Models\User;
use App\Support\InputFormat;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Write-path counterpart of the `permissions` metadata (spec 0004): resolves
 * the resource's ResourceAuthorization and rejects, with a 422 validator
 * error, any submitted key that maps to a non-editable field for the current
 * actor + target model. The same resolver that computes the metadata a
 * FormRequest's caller sees also guards the write, so a manipulated frontend
 * cannot bypass it.
 *
 * Concrete FormRequests declare which resource + model they target via
 * authorizationResource()/authorizationModel(), and call enforceFieldPermissions()
 * from their OWN withValidator() alongside their existing value-level rules —
 * this concern composes, it never replaces withValidator().
 *
 * CHANGE-based (spec 0008, replacing the former presence-based gate): a
 * non-editable field is only rejected when the submitted value actually
 * DIFFERS from what is currently persisted. A plain presence check would
 * reject the shared personal-data form, which always re-submits its whole
 * buffered `personal_data.*` object regardless of which parts changed; a
 * value-diff lets an untouched, non-editable section pass through as a
 * harmless no-op while still blocking a real change.
 */
trait EnforcesFieldPermissions
{
    /** The owned child section whose rows carry a channel-formatted `value`. */
    private const string CONTACTS_FIELD = 'contacts';

    /**
     * The `{resource}` key registered in config/authorization.php.
     */
    abstract protected function authorizationResource(): string;

    /**
     * The model instance being written, or null on create (store).
     */
    abstract protected function authorizationModel(): ?Model;

    /**
     * Reject every submitted key mapped to a non-editable field whose value
     * actually changed.
     *
     * Skipped when the actor lacks the resource's base write ability
     * (create/update): in that case the base CRUD authorization (enforced in
     * the controller via the Policy) is the relevant failure — a 403, not a
     * field-level 422 — so this never fires ahead of it.
     */
    protected function enforceFieldPermissions(Validator $validator): void
    {
        /** @var User $actor */
        $actor = $this->user();
        $resource = $this->authorizationResource();
        $model = $this->authorizationModel();

        $baseAbility = $model === null ? 'create' : 'update';

        if (! $actor->can("{$resource}.{$baseAbility}")) {
            return;
        }

        $authorization = app(AuthorizationRegistry::class)->resolve($resource);

        foreach ($authorization->fieldPermissions($actor, $model) as $field => $permission) {
            /** @var FieldPermission $permission */
            if ($permission->editable || ! $this->has($field)) {
                continue;
            }

            if ($this->fieldValueChanged($field, $model)) {
                $validator->errors()->add($field, 'field not editable');
            }
        }
    }

    /**
     * Whether the value submitted at $field's dot-path differs from what is
     * currently persisted on $model.
     */
    private function fieldValueChanged(string $field, ?Model $model): bool
    {
        $current = $this->canonicalize($field, $this->currentFieldValue($model, $field));
        $submitted = $this->projectSubmittedValue($this->input($field), $current);

        return $this->normalize($submitted) !== $this->normalize($current);
    }

    /**
     * Puts the PERSISTED value in the same canonical shape the write path gives
     * the submitted one (see FormatsPersonalDataInput, user directive
     * 2026-07-23) before the two are compared.
     *
     * Without this the guard would read a mere re-spelling as a change: a row
     * stored as `+39 333 0000000` before the rule existed, resubmitted
     * untouched by the shared form, now arrives as `+393330000000` — and a
     * LOCKED field would 422, blocking edits to unrelated parts of the same
     * form. The comparison must answer "did the actor change this value", not
     * "was it typed the way we store it today".
     */
    private function canonicalize(string $field, mixed $current): mixed
    {
        $leaf = Str::afterLast($field, '.');

        if ($leaf === self::CONTACTS_FIELD && is_array($current)) {
            return array_map($this->canonicalizeContactRow(...), $current);
        }

        if (! is_string($current)) {
            return $current;
        }

        // The card's type decides the shape of `tax_code`; it is read from the
        // SUBMITTED payload at the same path, exactly like the write path does.
        $isCompany = $this->input(Str::beforeLast($field, '.').'.type') === PersonalDataTypeEnum::Company->value;

        return InputFormat::identityField($leaf, $current, $isCompany);
    }

    /**
     * @param  mixed  $row  a projected contact row (see readNestedPath()'s section branch)
     */
    private function canonicalizeContactRow(mixed $row): mixed
    {
        if (! is_array($row) || ! isset($row['value']) || ! is_string($row['value'])) {
            return $row;
        }

        // The persisted side reads the model's CAST attribute, so the type
        // arrives as the enum itself, not as its raw value.
        $rawType = $row['type'] ?? null;
        $type = $rawType instanceof ContactTypeEnum
            ? $rawType
            : ContactTypeEnum::tryFrom(is_string($rawType) ? $rawType : '');

        if ($type !== null) {
            $row['value'] = InputFormat::contactValue($type, $row['value']);
        }

        return $row;
    }

    /**
     * The currently persisted value for $field, read generically off $model
     * via its dot-path — no per-resource/per-field mapping lives here, so
     * this stays correct for every ResourceAuthorization without coupling the
     * trait to any concrete resource (e.g. `users`' morph `personal_data.*`):
     *
     *  - a bare key (no dot) reads either a plain attribute, or — when it
     *    names a relation (e.g. `roles`) — the set of related keys (a
     *    to-many reference field's identity is WHICH rows it points at);
     *  - a dot-path walks each segment as an Eloquent relation (snake_case
     *    key -> camelCase method) until the last segment, which is read as a
     *    plain attribute UNLESS it is itself a relation, in which case an
     *    owned child section (e.g. `personal_data.contacts`) is projected to
     *    each related row's OWN `$fillable` attributes — the model's
     *    mass-assignment surface doubles as the semantic comparison shape,
     *    with no field list hardcoded here.
     *
     * On create ($model === null) there is nothing persisted yet, so every
     * field reads as null — any non-empty submission of a non-editable field
     * then counts as a change (spec 0008).
     */
    protected function currentFieldValue(?Model $model, string $field): mixed
    {
        if ($model === null) {
            return null;
        }

        if (! str_contains($field, '.')) {
            return $this->readTopLevel($model, $field);
        }

        return $this->readNestedPath($model, explode('.', $field));
    }

    /**
     * A bare (non-dotted) field: a plain attribute, or — for a to-many
     * relation (e.g. `roles`) — the related rows' identity (primary keys), a
     * reference field's semantic value being WHICH rows it points at. No
     * current catalogue field is a to-one top-level relation; add that branch
     * if/when one is introduced (YAGNI).
     *
     * `isRelation()` rather than a bare `method_exists()`: an Attribute-style
     * accessor is a method of the SAME camelCase name as its field (e.g.
     * Opportunity::operatorId() for the virtual `operator_id`), so
     * method_exists alone reads it as a relation candidate and invoking it
     * fatals — it is protected and takes no arguments. Eloquent's own check
     * excludes attribute mutators, which is exactly the distinction needed.
     */
    private function readTopLevel(Model $model, string $key): mixed
    {
        $relationMethod = Str::camel($key);

        if (! $model->isRelation($relationMethod) || ! $model->{$relationMethod}() instanceof Relation) {
            return $model->getAttribute($key);
        }

        return $model->{$relationMethod}->map(static fn (Model $row): int|string => $row->getKey())->all();
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function readNestedPath(Model $model, array $segments): mixed
    {
        $key = array_shift($segments);
        $relationMethod = Str::camel($key);
        // Same accessor-vs-relation distinction as readTopLevel() above.
        $isRelation = $model->isRelation($relationMethod) && $model->{$relationMethod}() instanceof Relation;

        if (! $isRelation) {
            return $segments === [] ? $model->getAttribute($key) : null;
        }

        $value = $model->{$relationMethod};

        if ($segments === []) {
            // The leaf of a nested dot-path is always a to-many owned SECTION
            // in the current catalogue (e.g. `personal_data.contacts`); a
            // to-one relation leaf would fall through unprojected (compares
            // as a raw Model, i.e. conservatively "always changed" — no
            // current field exercises that path, YAGNI).
            return $value instanceof Collection
                ? $value->map(fn (Model $row): array => $this->fillableAttributes($row))->all()
                : $value;
        }

        return $value instanceof Model ? $this->readNestedPath($value, $segments) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fillableAttributes(Model $model): array
    {
        return collect($model->getFillable())
            ->mapWithKeys(fn (string $attribute): array => [$attribute => $model->{$attribute}])
            ->all();
    }

    /**
     * When $current is a projected row-collection (a list of associative
     * arrays — see readNestedPath()'s section branch), the raw submitted rows
     * carry extra request-only keys (e.g. `id`) that are irrelevant to "did
     * the content change". Projecting the submitted rows onto the SAME key
     * set learned from $current's own first row keeps the comparison
     * semantic and symmetric. A count mismatch (rows added/removed) already
     * differs regardless of projection, so this only matters when $current is
     * non-empty; nothing to project otherwise.
     */
    private function projectSubmittedValue(mixed $submitted, mixed $current): mixed
    {
        if (! is_array($current) || ! is_array($current[0] ?? null) || ! is_array($submitted)) {
            return $submitted;
        }

        $keys = array_keys($current[0]);

        return array_map(
            static fn (mixed $row): array => array_merge(array_fill_keys($keys, null), Arr::only((array) $row, $keys)),
            $submitted,
        );
    }

    /**
     * Scalar-friendly normalization so equivalent values compare equal
     * regardless of representation: a backed enum vs its raw value, a
     * DateTimeInterface vs a "Y-m-d" string, and an order-insensitive,
     * key-order-insensitive shape for arrays/lists (row sections compare by
     * content, not by submission order — spec 0008 AC-007).
     */
    private function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_array($value)) {
            $normalized = $this->normalizeArray($value);

            // An empty section (no rows / no roles) is "nothing", same as
            // null/'' — this matters when $model has no owned relation yet
            // (e.g. no personal_data card): the current side reads as null,
            // and a submission that adds no rows must still compare equal.
            return $normalized === [] ? null : $normalized;
        }

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function normalizeArray(array $value): array
    {
        $normalized = array_map(fn (mixed $item): mixed => $this->normalize($item), $value);

        if (! array_is_list($normalized)) {
            ksort($normalized);

            return $normalized;
        }

        usort($normalized, static fn (mixed $a, mixed $b): int => json_encode($a) <=> json_encode($b));

        return $normalized;
    }
}
