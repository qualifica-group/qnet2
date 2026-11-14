<?php

namespace App\Services\Merge;

use App\DataObjects\Users\ProfileData;
use App\Models\City;
use App\Services\Merge\Collections\MergeCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What differs between merging registries and merging referents (spec 0394):
 * the fields, the discardable collections, the linking modules and the
 * references to move. The merge engine itself is entity-agnostic.
 */
abstract class MergeDefinition
{
    /** Hard cap on records in one merge, survivor included (D-1). */
    public const int MAX_RECORDS = 5;

    /** Morph alias of the entity (`registry`, `referent`). */
    abstract public function entity(): string;

    /** Table and authorization/custom-field key (`registries`, `referents`). */
    abstract public function table(): string;

    /** @return class-string<Model> */
    abstract public function modelClass(): string;

    /** @return list<MergeField> the fields specific to the entity, after the card ones */
    abstract protected function ownFields(): array;

    /** @return list<MergeCollection> */
    abstract public function collections(): array;

    /** @return list<LinkedSource> */
    abstract public function linkedSources(): array;

    abstract public function references(): MergeReferences;

    /**
     * Write the personal-data card the operator chose, deriving the record
     * `name` through the module's own profile writer (D-5).
     */
    abstract public function writeCard(Model $survivor, ProfileData $profile): void;

    /** Keep the record consistent after the chosen values are written. */
    abstract public function settle(Model $survivor): void;

    /** The survivor as the module's detail endpoint returns it. */
    abstract public function loadTree(Model $survivor): Model;

    /**
     * @param  array<string, mixed>  $permissions
     */
    abstract public function resource(Model $survivor, array $permissions): JsonResource;

    /**
     * @return list<MergeField>
     */
    final public function fields(): array
    {
        return [
            new MergeField('type', MergeField::TYPE_ENUM, onCard: true),
            new MergeField('first_name', MergeField::TYPE_TEXT, onCard: true),
            new MergeField('last_name', MergeField::TYPE_TEXT, onCard: true),
            new MergeField('company_name', MergeField::TYPE_TEXT, onCard: true),
            new MergeField('tax_code', MergeField::TYPE_TEXT, onCard: true),
            new MergeField('vat_number', MergeField::TYPE_TEXT, onCard: true),
            new MergeField('sdi_code', MergeField::TYPE_TEXT, onCard: true),
            new MergeField('birth_date', MergeField::TYPE_DATE, onCard: true),
            new MergeField('gender', MergeField::TYPE_ENUM, onCard: true),
            new MergeField('birth_city_id', MergeField::TYPE_RELATION, onCard: true, relationModel: City::class),
            new MergeField('residence_city_id', MergeField::TYPE_RELATION, onCard: true, relationModel: City::class),
            ...$this->ownFields(),
        ];
    }

    final public function collection(string $key): ?MergeCollection
    {
        foreach ($this->collections() as $collection) {
            if ($collection->key() === $key) {
                return $collection;
            }
        }

        return null;
    }
}
