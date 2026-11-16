<?php

namespace App\Services\Merge;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The write rules a normal edit of the survivor enforces, applied to the merge
 * (spec 0394, D-2): field-level permissions (EnforcesFieldPermissions on the
 * Update requests) and the length cap of the long texts.
 */
final class MergeWriteGuard
{
    /** Same cap as the `max:5000` of the Update requests on the long texts. */
    public const int MAX_TEXT_LENGTH = 5000;

    /** Field-permission key that governs each collection a merge adds to. */
    private const array COLLECTION_PERMISSIONS = [
        'contacts' => 'personal_data.contacts',
        'addresses' => 'personal_data.addresses',
        'sectors' => 'sector_ids',
        'referents' => 'referent_ids',
        'managers' => 'manager_slots',
    ];

    public function __construct(
        private readonly AuthorizationRegistry $authorization,
        private readonly MergeFieldReader $reader,
    ) {}

    /**
     * @param  Collection<int, Model>  $records  survivor first, then the duplicates
     * @param  array<string, string|int|float|bool|null>  $values  the resolved final values
     *
     * @throws ValidationException
     */
    public function assertAllowed(User $actor, MergeDefinition $definition, Collection $records, MergeRequestData $data, array $values): void
    {
        $survivor = $records->first();
        $permissions = $this->authorization->resolve($definition->table())->fieldPermissions($actor, $survivor);
        $errors = [];

        foreach ($definition->fields() as $field) {
            $permissionKey = $field->onCard ? "personal_data.{$field->key}" : $field->key;
            $changes = $this->normalized($values[$field->key]) !== $this->normalized($this->reader->value($field, $survivor));

            if ($changes && ! $this->editable($permissions, $permissionKey)) {
                $errors["fields.{$field->key}"] = __('You cannot change this field.');
            }

            if ($field->combinable && mb_strlen((string) $values[$field->key]) > self::MAX_TEXT_LENGTH) {
                $errors["fields.{$field->key}"] = __('The combined text is longer than :max characters: keep one of the texts.', ['max' => self::MAX_TEXT_LENGTH]);
            }
        }

        foreach ($definition->collections() as $collection) {
            $permissionKey = self::COLLECTION_PERMISSIONS[$collection->key()] ?? null;

            if ($permissionKey === null || $this->editable($permissions, $permissionKey)) {
                continue;
            }

            $moving = MergeItemDeduper::toMove($collection->items($records), (int) $survivor->getKey(), $data->discard[$collection->key()] ?? []);

            if ($moving !== []) {
                $errors["discard.{$collection->key()}"] = __('You cannot add these elements to the kept record: discard them.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, FieldPermission>  $permissions
     */
    private function editable(array $permissions, string $key): bool
    {
        return ! isset($permissions[$key]) || $permissions[$key]->editable;
    }

    private function normalized(string|int|float|bool|null $value): string|int|float|bool|null
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
