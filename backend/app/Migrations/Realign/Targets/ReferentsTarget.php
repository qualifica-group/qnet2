<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Enums\GenderEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Migrations\Realign\AbstractLegacyRealignTarget;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\OperationalLegacyValues;
use App\Migrations\Realign\Support\OperationalLookupNames;
use App\Migrations\Realign\Support\ReferentLegacyProfile;
use App\Migrations\Realign\Support\RegistryProfileAligner;
use App\Models\Referent;
use App\Models\ReferentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Aligns a referent already imported (spec 0375 D-11): card, primary channels and
 * address, type, scope and notes. Its links to users, suppliers and registries stay.
 */
final class ReferentsTarget extends AbstractLegacyRealignTarget
{
    use OperationalLegacyValues;

    private const array CARD_COLUMNS = ['first_name', 'last_name', 'tax_code', 'vat_number', 'birth_date', 'gender'];

    public function __construct(
        private readonly ReferentLegacyProfile $profiles,
        private readonly RegistryProfileAligner $aligner,
        private readonly OperationalLookupNames $names,
    ) {}

    public function source(): string
    {
        return 'referents';
    }

    protected function modelClass(): string
    {
        return Referent::class;
    }

    public function label(Model $record): string
    {
        return (string) $record->getRawOriginal('name');
    }

    /**
     * The referent's own entries count only with an author: every imported referent
     * carries the author-less `updated` of its derived name, logged during the import
     * (14.440 out of 14.440 on real data), and it is not a change made on qnet-2.
     */
    public function touchSubjects(Model $record): array
    {
        $changedByPerson = Referent::query()
            ->whereKey($record->getKey())
            ->whereExists(fn (QueryBuilder $activity): QueryBuilder => $activity
                ->selectRaw('1')
                ->from('activity_log')
                ->where('subject_type', $record->getMorphClass())
                ->whereColumn('subject_id', 'referents.id')
                ->whereNotNull('causer_id'))
            ->select('id');

        return [[Referent::class, $changedByPerson], ...$this->aligner->touchSubjects($record)];
    }

    public function snapshot(Model $record): array
    {
        return [
            ...$this->columns($record, ['name', 'contact_scope', 'notes']),
            'type' => $this->names->name(ReferentType::class, $record->getRawOriginal('referent_type_id')),
            ...$this->aligner->snapshot($record, self::CARD_COLUMNS),
        ];
    }

    /**
     * @param  Referent  $record
     */
    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        $warnings = [];

        // Step 1: the card, channels and address, with the legacy's own mapping.
        $profile = $this->profiles->profile($legacy, $warnings);
        $changed = $this->aligner->align($record, $profile, $this->cardColumns($legacy, $warnings), $this->blankToNull($legacy['street'] ?? null) !== null);

        // Step 2: the referent's own fields, kept when the legacy value is unknown here.
        $record->forceFill([
            'notes' => $this->blankToNull($legacy['notes'] ?? null),
            ...$this->scope($legacy, $warnings),
            ...$this->type($legacy, $warnings),
        ]);

        // Step 3: one quiet save, only when something differs.
        if ($record->isDirty() || $changed) {
            $this->saveWithLegacyTimestamp($record, $legacy);
            array_walk($warnings, static fn (string $warning) => $notes->warn($warning));
        }
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return list<string>
     */
    private function cardColumns(array $legacy, array &$warnings): array
    {
        $gender = $this->blankToNull($legacy['gender'] ?? null);

        if ($gender === null || GenderEnum::tryFrom($gender) !== null) {
            return self::CARD_COLUMNS;
        }

        $warnings[] = "Unresolved gender '{$gender}', left as it is.";

        return array_values(array_diff(self::CARD_COLUMNS, ['gender']));
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, string>
     */
    private function scope(array $legacy, array &$warnings): array
    {
        $scope = $this->blankToNull($legacy['contact_scope'] ?? null);

        if ($scope === null) {
            return [];
        }

        if (ReferentContactScopeEnum::tryFrom($scope) === null) {
            $warnings[] = "Unresolved contact_scope '{$scope}', left as it is.";

            return [];
        }

        return ['contact_scope' => $scope];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, int|null>
     */
    private function type(array $legacy, array &$warnings): array
    {
        $reference = $legacy['referent_type_id'] ?? null;

        if (! $this->isSent($reference)) {
            return ['referent_type_id' => null];
        }

        $typeId = ReferentType::query()->where('old_id', $reference)->value('id');

        if ($typeId === null) {
            $warnings[] = "Unresolved referent_type_id (external id {$reference}), left as it is.";

            return [];
        }

        return ['referent_type_id' => (int) $typeId];
    }
}
