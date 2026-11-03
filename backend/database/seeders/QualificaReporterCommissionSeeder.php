<?php

namespace Database\Seeders;

use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use App\Services\ReferentService;
use App\Services\UserService;
use Carbon\CarbonImmutable;
use Database\Seeders\QualificaCatalog\ReporterCommissionCatalogue as Catalogue;
use Database\Seeders\QualificaCatalog\ReporterCommissionRules;
use Database\Seeders\QualificaCatalog\ReporterCommissionTargets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The client's reporters and their personal commission rules (user directives
 * 2026-10-30 and 2026-11-03). Step 13 of QualificaProductionDataSeeder, and
 * runnable on its own (`php artisan db:seed --class=QualificaReporterCommissionSeeder`):
 * every run makes the database converge on ReporterCommissionCatalogue, so a
 * rate edited there takes effect on the next run.
 *
 * A reporter with a legacy id is looked up by `old_id`; if the referents have
 * not been migrated yet it is skipped with a warning, so a re-run after the
 * migration completes it. A reporter without one is matched by name (case,
 * accents and word order ignored), created as an external "Segnalatore"
 * referent when missing, skipped with a warning when the name is ambiguous.
 * The referent gets the "Segnalatore" type when it has none and its notes
 * from the catalogue; its rules are synced by ReporterCommissionRules.
 */
class QualificaReporterCommissionSeeder extends Seeder
{
    /** @var array<string, list<int>>|null */
    private ?array $referentIdsByName = null;

    /** @var array<string, true> */
    private array $warnings = [];

    public function __construct(
        private readonly ReferentService $referents,
        private readonly ReporterCommissionTargets $targets,
        private readonly ReporterCommissionRules $rules,
    ) {}

    public function run(): void
    {
        $actor = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', UserService::PRIVILEGED_ROLE))
            ->orderBy('id')
            ->first();

        if ($actor === null) {
            $this->command?->warn('Reporter commissions skipped: no super-admin user to create the referents as.');

            return;
        }

        // Step 1: the categories and products every column maps to, resolved once.
        $targets = $this->targets->resolve($this->warn(...));
        $typeId = ReferentType::query()->where('name', Catalogue::REFERENT_TYPE)->value('id');

        // Step 2: the rules left behind by reporters deleted from the UI.
        $totals = ['created' => 0, 'updated' => 0, 'removed' => $this->rules->removeOrphans()];

        foreach (Catalogue::REPORTERS as $reporter) {
            // Step 3: the reporter, found or created, with its type and notes.
            $referent = $this->resolveReferent($reporter, $actor, $typeId);

            if ($referent === null) {
                continue;
            }

            $this->syncProfile($referent, $reporter, $typeId);

            // Step 4: its rules converge on the catalogue.
            foreach ($this->rules->sync($referent, $reporter['rates'], $targets, $reporter['note'] ?? null) as $count => $value) {
                $totals[$count] += $value;
            }
        }

        $this->command?->info(sprintf('Reporter commissions: %d created, %d updated, %d removed.', $totals['created'], $totals['updated'], $totals['removed']));
    }

    /**
     * @param  array<string, mixed>  $reporter
     */
    private function resolveReferent(array $reporter, User $actor, ?int $typeId): ?Referent
    {
        if (isset($reporter['old_id'])) {
            $referent = Referent::query()->where('old_id', $reporter['old_id'])->first();

            if ($referent === null) {
                $this->warn(sprintf('Row %d: legacy referent %d not migrated yet, skipped.', $reporter['row'], $reporter['old_id']));
            }

            return $referent;
        }

        $card = isset($reporter['company_name'])
            ? new CreatePersonalData(type: PersonalDataTypeEnum::Company, companyName: $reporter['company_name'])
            : new CreatePersonalData(type: PersonalDataTypeEnum::Individual, firstName: $reporter['first_name'], lastName: $reporter['last_name']);

        $matches = $this->referentIdsByName()[$this->nameKey($card->displayName())] ?? [];

        if (count($matches) > 1) {
            $this->warn(sprintf('Row %d: %d referents named "%s", skipped.', $reporter['row'], count($matches), $card->displayName()));

            return null;
        }

        if ($matches !== []) {
            return Referent::query()->find($matches[0]);
        }

        return $this->referents->create(
            $actor,
            new CreateReferentData(
                referentTypeId: $typeId,
                contactScope: ReferentContactScopeEnum::External->value,
                notes: $this->notes($reporter),
            ),
            new ProfileData(card: $card, contacts: $this->contacts($reporter) ?: null),
        );
    }

    /**
     * @param  array<string, mixed>  $reporter
     */
    private function syncProfile(Referent $referent, array $reporter, ?int $typeId): void
    {
        $referent->notes = $this->notes($reporter);

        if ($referent->referent_type_id === null) {
            $referent->referent_type_id = $typeId;
        }

        if ($referent->isDirty()) {
            $referent->save();
        }
    }

    /**
     * The NOTE column, preceded by the commercial supervisor and the contact
     * person, followed by the rates that start later (AVV/GAR from 2027).
     *
     * @param  array<string, mixed>  $reporter
     */
    private function notes(array $reporter): ?string
    {
        $lines = [
            isset($reporter['supervisor']) ? 'Supervisore commerciale: '.$reporter['supervisor'] : null,
            isset($reporter['contact']) ? 'Referente: '.$reporter['contact'] : null,
            $reporter['note'] ?? null,
        ];

        foreach ($reporter['rates'] as $column => $value) {
            $startsOn = Catalogue::COLUMNS[$column]['valid_from'] ?? null;

            if ($startsOn !== null) {
                $lines[] = sprintf('%s: %s%% dal %s.', Catalogue::COLUMNS[$column]['label'], $value, CarbonImmutable::parse($startsOn)->format('d/m/Y'));
            }
        }

        return implode("\n", array_filter($lines)) ?: null;
    }

    /**
     * @param  array<string, mixed>  $reporter
     * @return list<ContactInput>
     */
    private function contacts(array $reporter): array
    {
        $contacts = [];

        foreach (['email' => ContactTypeEnum::Email, 'phone' => ContactTypeEnum::Phone] as $field => $type) {
            if (isset($reporter[$field])) {
                $contacts[] = new ContactInput(id: null, data: new CreateContact(type: $type, value: $reporter[$field], isPrimary: true));
            }
        }

        return $contacts;
    }

    /**
     * Every referent id by normalised name, read once.
     *
     * @return array<string, list<int>>
     */
    private function referentIdsByName(): array
    {
        if ($this->referentIdsByName === null) {
            $this->referentIdsByName = [];

            foreach (Referent::query()->toBase()->get(['id', 'name']) as $row) {
                $this->referentIdsByName[$this->nameKey((string) $row->name)][] = (int) $row->id;
            }
        }

        return $this->referentIdsByName;
    }

    private function nameKey(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($words);

        return implode(' ', $words);
    }

    private function warn(string $message): void
    {
        if (! isset($this->warnings[$message])) {
            $this->warnings[$message] = true;
            $this->command?->warn($message);
        }
    }
}
