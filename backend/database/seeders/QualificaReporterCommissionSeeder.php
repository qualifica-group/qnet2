<?php

namespace Database\Seeders;

use App\DataObjects\CommissionConfigurations\CreateCommissionConfigurationData;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Models\CommissionConfiguration;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use App\Services\CommissionConfigurationService;
use App\Services\ReferentService;
use App\Services\UserService;
use Database\Seeders\QualificaCatalog\ReporterCommissionCatalogue as Catalogue;
use Illuminate\Database\Seeder;

/**
 * The client's reporters and their personal commission rules (user directive
 * 2026-10-30): one REPORTER rule per reporter and category, PRODUCT_CATEGORY
 * scope, percentage, valid from Catalogue::VALID_FROM.
 *
 * A reporter with a legacy id is looked up by `old_id` and never modified; if
 * the referents have not been migrated yet it is skipped with a warning, so a
 * re-run after the migration completes it. A reporter without one is created
 * as an external "Segnalatore" referent, and found again by name on a re-run.
 * A rule that already exists for the same reporter and category is left
 * alone: an edit made from the UI survives a re-run.
 */
class QualificaReporterCommissionSeeder extends Seeder
{
    public function __construct(
        private readonly ReferentService $referents,
        private readonly CommissionConfigurationService $rules,
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

        // Step 1: the categories every column maps to, resolved once.
        $categoryIds = $this->resolveCategoryIds();
        $typeId = ReferentType::query()->where('name', Catalogue::REFERENT_TYPE)->value('id');

        foreach (Catalogue::REPORTERS as $reporter) {
            // Step 2: the reporter, found or created.
            $referent = $this->resolveReferent($reporter, $actor, $typeId);

            if ($referent === null) {
                continue;
            }

            // Step 3: one rule per category of each rate.
            foreach ($reporter['rates'] as $column => $value) {
                foreach (Catalogue::CATEGORIES[$column] as $categoryName) {
                    $categoryId = $categoryIds[$categoryName] ?? null;

                    if ($categoryId !== null) {
                        $this->ensureRule($referent, $categoryId, $categoryName, $value, $this->ruleNote($column, $reporter['note'] ?? null));
                    }
                }
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function resolveCategoryIds(): array
    {
        $names = array_merge(...array_values(Catalogue::CATEGORIES));
        $ids = ProductCategory::query()->whereIn('name', $names)->pluck('id', 'name')->all();

        foreach (array_diff($names, array_keys($ids)) as $missing) {
            $this->command?->warn(sprintf('Category "%s" not found: its reporter commissions are skipped.', $missing));
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $reporter
     */
    private function resolveReferent(array $reporter, User $actor, ?int $typeId): ?Referent
    {
        if (isset($reporter['old_id'])) {
            $referent = Referent::query()->where('old_id', $reporter['old_id'])->first();

            if ($referent === null) {
                $this->command?->warn(sprintf('Row %d: legacy referent %d not migrated yet, skipped.', $reporter['row'], $reporter['old_id']));
            }

            return $referent;
        }

        $card = isset($reporter['company_name'])
            ? new CreatePersonalData(type: PersonalDataTypeEnum::Company, companyName: $reporter['company_name'])
            : new CreatePersonalData(type: PersonalDataTypeEnum::Individual, firstName: $reporter['first_name'], lastName: $reporter['last_name']);

        $existing = Referent::query()->whereNull('old_id')->where('name', $card->displayName())->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->referents->create(
            $actor,
            new CreateReferentData(
                referentTypeId: $typeId,
                contactScope: ReferentContactScopeEnum::External->value,
                notes: $reporter['note'] ?? null,
            ),
            new ProfileData(card: $card, contacts: $this->contacts($reporter) ?: null),
        );
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

    private function ensureRule(Referent $referent, int $categoryId, string $categoryName, string $value, ?string $note): void
    {
        $exists = CommissionConfiguration::query()
            ->where('recipient_role', CommissionRecipientRole::Reporter)
            ->where('application_scope', CommissionApplicationScope::ProductCategory)
            ->where('product_category_id', $categoryId)
            ->where('recipient_type', $referent->getMorphClass())
            ->where('recipient_id', $referent->id)
            ->exists();

        if ($exists) {
            return;
        }

        $this->rules->create(new CreateCommissionConfigurationData(
            name: sprintf('%s - %s - %s', Catalogue::RULE_NAME_PREFIX, $referent->name, $categoryName),
            recipientRole: CommissionRecipientRole::Reporter,
            applicationScope: CommissionApplicationScope::ProductCategory,
            productCategoryId: $categoryId,
            productId: null,
            recipientId: $referent->id,
            recipientType: $referent->getMorphClass(),
            commissionType: CommissionType::Percentage,
            value: $value,
            priority: 0,
            validFrom: Catalogue::VALID_FROM,
            validUntil: null,
            status: CommissionConfigurationStatus::Active,
            internalNote: $note,
        ));
    }

    private function ruleNote(string $column, ?string $note): ?string
    {
        $parts = array_filter([$column === 'pdr' ? Catalogue::MAINTENANCE_NOTE : null, $note]);

        return $parts === [] ? null : implode(' ', $parts);
    }
}
