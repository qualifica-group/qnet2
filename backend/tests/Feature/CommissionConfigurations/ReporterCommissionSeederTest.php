<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Models\CommissionConfiguration;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\ReferentType;
use Database\Seeders\QualificaCatalog\ReporterCommissionCatalogue;
use Database\Seeders\QualificaReporterCommissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Antonio Carbone, spreadsheet row 3: 20% on every column, 10% on PDR 125 / SA 8000.
const CARBONE_OLD_ID = 17552;

beforeEach(function () {
    migrationsSuperAdminActor();

    foreach (array_merge(...array_values(ReporterCommissionCatalogue::CATEGORIES)) as $name) {
        ProductCategory::factory()->create(['name' => $name]);
    }

    $this->reporterType = ReferentType::factory()->create(['name' => ReporterCommissionCatalogue::REFERENT_TYPE]);
});

function reporterRules(Referent $referent): array
{
    return CommissionConfiguration::query()
        ->with('productCategory')
        ->where('recipient_type', 'referent')
        ->where('recipient_id', $referent->id)
        ->get()
        ->mapWithKeys(fn (CommissionConfiguration $rule) => [$rule->productCategory->name => $rule])
        ->all();
}

it('gives a migrated reporter one percentage rule per category, leaving the referent untouched', function () {
    $carbone = Referent::factory()->create(['name' => 'Antonio Carbone', 'referent_type_id' => null]);
    $carbone->old_id = CARBONE_OLD_ID;
    $carbone->save();

    $this->seed(QualificaReporterCommissionSeeder::class);

    $rules = reporterRules($carbone);

    expect(array_map(fn (CommissionConfiguration $rule) => $rule->value, $rules))->toEqualCanonicalizing([
        'ISO' => '20.0000', 'SOA' => '20.0000', 'Sicurezza' => '20.0000', 'GDPR' => '20.0000',
        'Finanza Agevolata - FNC' => '20.0000', 'ISO - UNI PDR 125' => '10.0000', 'ISO - SA8000' => '10.0000',
    ])
        ->and($rules['ISO']->recipient_role)->toBe(CommissionRecipientRole::Reporter)
        ->and($rules['ISO']->application_scope)->toBe(CommissionApplicationScope::ProductCategory)
        ->and($rules['ISO']->commission_type)->toBe(CommissionType::Percentage)
        ->and($rules['ISO']->valid_from->toDateString())->toBe(ReporterCommissionCatalogue::VALID_FROM)
        ->and($rules['ISO - SA8000']->internal_note)->toStartWith(ReporterCommissionCatalogue::MAINTENANCE_NOTE)
        ->and($carbone->fresh()->referent_type_id)->toBeNull();
});

it('creates a missing reporter named after the NOME column, a company as a company card', function () {
    $this->seed(QualificaReporterCommissionSeeder::class);

    $artica = Referent::query()->where('name', 'Artica Ware Srl')->sole();
    $contacts = $artica->personalData->contacts->pluck('value', 'type.value')->all();

    expect($artica->personalData->type)->toBe(PersonalDataTypeEnum::Company)
        ->and($artica->referent_type_id)->toBe($this->reporterType->id)
        ->and($artica->contact_scope)->toBe(ReferentContactScopeEnum::External)
        ->and($artica->notes)->toContain('Referente: Stefano Dari')
        ->and($contacts)->toBe([ContactTypeEnum::Email->value => 'stefanodari@hotmail.com', ContactTypeEnum::Phone->value => '349 471 3811'])
        ->and(array_keys(reporterRules($artica)))->toEqualCanonicalizing(['ISO', 'SOA', 'Sicurezza', 'GDPR', 'ISO - UNI PDR 125', 'ISO - SA8000']);
});

it('skips a legacy reporter that is not migrated yet instead of creating it', function () {
    $this->seed(QualificaReporterCommissionSeeder::class);

    expect(Referent::query()->where('name', 'Antonio Carbone')->exists())->toBeFalse();
});

it('converges on a re-run and keeps a rule edited in the meantime', function () {
    $this->seed(QualificaReporterCommissionSeeder::class);
    $referents = Referent::query()->count();
    $rules = CommissionConfiguration::query()->count();
    $edited = reporterRules(Referent::query()->where('name', 'Claudio Falco')->sole())['ISO'];
    $edited->update(['value' => '12']);

    $this->seed(QualificaReporterCommissionSeeder::class);

    expect(Referent::query()->count())->toBe($referents)
        ->and(CommissionConfiguration::query()->count())->toBe($rules)
        ->and($edited->fresh()->value)->toBe('12.0000');
});
