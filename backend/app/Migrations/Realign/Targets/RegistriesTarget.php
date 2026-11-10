<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldWriter;
use App\Enums\AgreementStatusEnum;
use App\Enums\SizeClassEnum;
use App\Migrations\Realign\AbstractLegacyRealignTarget;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\OperationalLegacyValues;
use App\Migrations\Realign\Support\RegistryLegacyProfile;
use App\Migrations\Realign\Support\RegistryProfileAligner;
use App\Models\CustomFieldValue;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Tag;
use App\Models\User;
use App\Support\ManagerPositions;
use App\Support\PositionalPivotSync;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Aligns an anagrafica already imported (spec 0375 D-11): card, primary channels and
 * address, its own fields, supervisor, managers, sectors and tags. Source, commercial,
 * reporter and linked referents stay: they carry the commercial attribution.
 */
final class RegistriesTarget extends AbstractLegacyRealignTarget
{
    use OperationalLegacyValues;

    private const string ENTITY_TYPE = 'registries';

    private const string TAGS_FIELD = 'tags';

    private const array CARD_COLUMNS = ['type', 'first_name', 'last_name', 'company_name', 'tax_code', 'vat_number', 'sdi_code'];

    private const array COLUMNS = ['name', 'vat_group', 'is_supplier', 'is_qualified_supplier', 'agreement_status', 'agreement_notes', 'size_class', 'employee_count'];

    public function __construct(
        private readonly RegistryLegacyProfile $profiles,
        private readonly RegistryProfileAligner $aligner,
        private readonly CustomFieldProvider $customFields,
        private readonly CustomFieldWriter $customFieldWriter,
    ) {}

    public function source(): string
    {
        return 'registries';
    }

    protected function modelClass(): string
    {
        return Registry::class;
    }

    public function label(Model $record): string
    {
        return (string) $record->getRawOriginal('name');
    }

    public function touchSubjects(Model $record): array
    {
        return [...parent::touchSubjects($record), ...$this->aligner->touchSubjects($record)];
    }

    /**
     * @param  Registry  $record
     */
    public function snapshot(Model $record): array
    {
        return [
            ...$this->columns($record, self::COLUMNS),
            'supervisor' => $this->personName(User::query()->find($record->getRawOriginal('supervisor_id'))),
            'managers' => $this->peopleNames($record->managers()),
            'sectors' => $record->sectors()->pluck('sectors.name')->sort()->values()->implode(', '),
            'tags' => Tag::query()->whereIn('id', $this->currentTagIds($record))->pluck('name')->sort()->values()->implode(', '),
            ...$this->aligner->snapshot($record, self::CARD_COLUMNS),
        ];
    }

    /**
     * @param  Registry  $record
     */
    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        $warnings = [];

        // Step 1: anyone not migrated fails the row before any write (AC-019).
        $supervisorId = $this->requiredUserId($legacy['supervisor_user_id'] ?? null, 'Supervisor');
        $managerSlots = $this->requiredManagerSlots($legacy['manager_user_ids'] ?? [], $warnings);

        // Step 2: card, channels and address, with the import's own mapping.
        $changed = $this->aligner->align(
            $record,
            $this->profiles->profile($legacy, $warnings),
            self::CARD_COLUMNS,
            $this->blankToNull($legacy['street'] ?? null) !== null,
        );

        // Step 3: the registry's own fields and its relations, each written only when different.
        $record->forceFill([...$this->ownFields($legacy, $warnings), 'supervisor_id' => $supervisorId]);
        $changed = $this->syncManagers($record, $managerSlots) || $changed;
        $changed = $this->syncSectors($record, $legacy, $warnings) || $changed;
        $changed = $this->syncTags($record, $legacy, $warnings) || $changed;

        // Step 4: one quiet save, only when something differs.
        if ($record->isDirty() || $changed) {
            $this->saveWithLegacyTimestamp($record, $legacy);
            array_walk($warnings, static fn (string $warning) => $notes->warn($warning));
        }
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function ownFields(array $legacy, array &$warnings): array
    {
        $isSupplier = (bool) ($legacy['is_supplier'] ?? false);
        $employeeCount = $legacy['employee_count'] ?? null;

        return [
            'vat_group' => $this->blankToNull($legacy['vat_group'] ?? null),
            'is_supplier' => $isSupplier,
            // Only a supplier can be qualified, as RegistryService keeps it.
            'is_qualified_supplier' => $isSupplier && (bool) ($legacy['is_qualified_supplier'] ?? false),
            'agreement_notes' => $this->blankToNull($legacy['agreement_notes'] ?? null),
            'employee_count' => $employeeCount === null || $employeeCount === '' ? null : (int) $employeeCount,
            ...$this->enumField('agreement_status', AgreementStatusEnum::class, $legacy, $warnings),
            ...$this->enumField('size_class', SizeClassEnum::class, $legacy, $warnings),
        ];
    }

    /**
     * Blank clears the field; a value unknown to qnet-2 leaves it as it is.
     *
     * @param  class-string<BackedEnum>  $enumClass
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, string|null>
     */
    private function enumField(string $column, string $enumClass, array $legacy, array &$warnings): array
    {
        $value = $this->blankToNull($legacy[$column] ?? null);

        if ($value !== null && $enumClass::tryFrom($value) === null) {
            $warnings[] = "Unknown {$column} '{$value}', left as it is.";

            return [];
        }

        return [$column => $value];
    }

    /**
     * @param  array<int, int|null>  $slots
     */
    private function syncManagers(Registry $registry, array $slots): bool
    {
        $target = [];

        foreach (ManagerPositions::syncMap($slots) as $userId => $pivot) {
            $target[(int) $userId] = $pivot['position'];
        }

        $current = ManagerPositions::positionsOf($registry->managers()->get());
        ksort($current);
        ksort($target);

        if ($current === $target) {
            return false;
        }

        PositionalPivotSync::sync($registry->managers(), ManagerPositions::syncMap($slots));

        return true;
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     */
    private function syncSectors(Registry $registry, array $legacy, array &$warnings): bool
    {
        $sectorIds = $this->migratedIds(Sector::class, $legacy['sector_ids'] ?? [], 'sector_ids', $warnings);

        if ($sectorIds === null || $this->sameIds($registry->sectors()->pluck('sectors.id')->map(intval(...))->all(), $sectorIds)) {
            return false;
        }

        $registry->sectors()->sync($sectorIds);

        return true;
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     */
    private function syncTags(Registry $registry, array $legacy, array &$warnings): bool
    {
        $tagIds = $this->migratedIds(Tag::class, $legacy['tag_ids'] ?? [], 'tag_ids', $warnings);

        if ($tagIds === null || $this->sameIds($this->currentTagIds($registry), $tagIds)) {
            return false;
        }

        if ($this->customFields->definitionsFor(self::ENTITY_TYPE)->doesntContain('key', self::TAGS_FIELD)) {
            $warnings[] = 'Tags not aligned: the registries "'.self::TAGS_FIELD.'" custom field is not defined.';

            return false;
        }

        $this->customFieldWriter->write($registry, self::ENTITY_TYPE, [self::TAGS_FIELD => $tagIds]);

        return true;
    }

    /**
     * @return array<int, int>
     */
    private function currentTagIds(Registry $registry): array
    {
        $values = CustomFieldValue::query()
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('entity_id', $registry->getKey())
            ->value('values');

        return array_map(intval(...), (array) ($values[self::TAGS_FIELD] ?? []));
    }

    /**
     * The local ids, or null when one is not migrated: a partial list would
     * unlink a value that is still there, so the relation is left as it is.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $warnings
     * @return array<int, int>|null
     */
    private function migratedIds(string $modelClass, mixed $legacyIds, string $field, array &$warnings): ?array
    {
        $legacyIds = array_values(array_unique(array_filter((array) ($legacyIds ?? []), $this->isSent(...))));
        $ids = $modelClass::query()->whereIn('old_id', $legacyIds)->pluck('id')->map(intval(...))->all();

        if (count($ids) === count($legacyIds)) {
            return $ids;
        }

        $warnings[] = "Unresolved {$field}: left as they are.";

        return null;
    }
}
