<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteLineCommission;
use App\Models\Referent;
use App\Models\ReferentType;
use Database\Seeders\QualificaCatalog\ReporterCommissionCatalogue;
use Database\Seeders\QualificaCatalog\ReporterCommissionTargets;
use Database\Seeders\QualificaReporterCommissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Antonio Carbone, spreadsheet row 3 (supervisor Antonio Alvoni).
const CARBONE_OLD_ID = 17552;

/** Every category the catalogue names, children under the root they belong to. */
const REPORTER_TEST_TREE = [
    'ISO' => ['ISO - 9001', 'ISO - UNI PDR 125', 'ISO - SA8000'],
    'SOA' => [],
    'Sicurezza' => ['Sicurezza - Visite mediche'],
    'Finanza Agevolata' => ['Finanza Agevolata - FNC'],
    'Gare' => [],
    'GDPR' => ['GDPR - Nomina DPO'],
    'APL' => [],
];

/** The products of the test catalog, by category. */
const REPORTER_TEST_PRODUCTS = [
    'ISO - 9001' => ['Consulenza ISO 9001_1° Anno', 'Consulenza ISO 9001_2° Anno', 'Consulenza ISO 9001__3° Anno'],
    'ISO - UNI PDR 125' => ['Consulenza UNI PDR 125 _ 2° Anno', 'Consulenza UNI PDR 125 _ 3° Anno'],
    'ISO - SA8000' => ['Consulenza SA 8000_SORVEGLIANZA 6 MESI', 'Consulenza SA 8000_SORVEGLIANZA 12 MESI', 'Consulenza SA 8000_SORVEGLIANZA 24 MESI'],
    'GDPR - Nomina DPO' => ['DPO_1° anno', 'DPO_2° anno', 'DPO_3° anno'],
];

beforeEach(function () {
    migrationsSuperAdminActor();

    foreach (REPORTER_TEST_TREE as $root => $children) {
        $parent = ProductCategory::factory()->create(['name' => $root]);

        foreach ($children as $child) {
            $category = ProductCategory::factory()->create(['name' => $child, 'parent_id' => $parent->id]);

            foreach (REPORTER_TEST_PRODUCTS[$child] ?? [] as $product) {
                Product::factory()->create(['name' => $product, 'category_id' => $category->id]);
            }
        }
    }

    $this->reporterType = ReferentType::factory()->create(['name' => ReporterCommissionCatalogue::REFERENT_TYPE]);
});

/**
 * @return array<string, CommissionConfiguration> keyed by category or product name
 */
function reporterRules(Referent $referent): array
{
    return CommissionConfiguration::query()
        ->with(['productCategory', 'product'])
        ->where('recipient_type', 'referent')
        ->where('recipient_id', $referent->id)
        ->get()
        ->mapWithKeys(fn (CommissionConfiguration $rule) => [($rule->product ?? $rule->productCategory)->name => $rule])
        ->all();
}

function migratedCarbone(array $attributes = []): Referent
{
    $carbone = Referent::factory()->create(['name' => 'Antonio Carbone', 'referent_type_id' => null, ...$attributes]);
    $carbone->old_id = CARBONE_OLD_ID;
    $carbone->save();

    return $carbone;
}

it('gives a migrated reporter a rule per category and per 2nd/3rd-year product', function () {
    $carbone = migratedCarbone();

    $this->seed(QualificaReporterCommissionSeeder::class);

    $rules = reporterRules($carbone);

    expect(array_map(fn (CommissionConfiguration $rule) => $rule->value, $rules))->toEqualCanonicalizing([
        'ISO' => '20.0000', 'ISO - UNI PDR 125' => '10.0000', 'ISO - SA8000' => '10.0000', 'SOA' => '20.0000',
        'Sicurezza' => '20.0000', 'Sicurezza - Visite mediche' => '5.0000', 'Finanza Agevolata' => '15.0000',
        'Finanza Agevolata - FNC' => '20.0000', 'Gare' => '10.0000', 'GDPR' => '20.0000', 'APL' => '10.0000',
        'Consulenza ISO 9001_2° Anno' => '10.0000', 'Consulenza ISO 9001__3° Anno' => '10.0000',
        'Consulenza UNI PDR 125 _ 2° Anno' => '5.0000', 'Consulenza UNI PDR 125 _ 3° Anno' => '5.0000',
        'Consulenza SA 8000_SORVEGLIANZA 12 MESI' => '5.0000', 'Consulenza SA 8000_SORVEGLIANZA 24 MESI' => '5.0000',
        'DPO_2° anno' => '15.0000', 'DPO_3° anno' => '15.0000',
    ])
        ->and($rules['ISO']->recipient_role)->toBe(CommissionRecipientRole::Reporter)
        ->and($rules['ISO']->application_scope)->toBe(CommissionApplicationScope::ProductCategory)
        ->and($rules['ISO']->commission_type)->toBe(CommissionType::Percentage)
        ->and($rules['ISO']->valid_from->toDateString())->toBe(ReporterCommissionCatalogue::VALID_FROM)
        ->and($rules['DPO_2° anno']->application_scope)->toBe(CommissionApplicationScope::Product)
        ->and($rules['Gare']->valid_from->toDateString())->toBe('2027-01-01')
        ->and($rules['Gare']->internal_note)->toStartWith('AVV/GAR: in vigore dal 01/01/2027.');
});

it('gives the matched referent the reporter type and the notes of the spreadsheet', function () {
    $carbone = migratedCarbone();
    $commercialType = ReferentType::factory()->create(['name' => 'Commerciale']);
    $lettini = Referent::factory()->create(['name' => 'Francesco Lettini', 'referent_type_id' => $commercialType->id]);
    $lettini->old_id = 16481;
    $lettini->save();

    $this->seed(QualificaReporterCommissionSeeder::class);

    expect($carbone->fresh()->referent_type_id)->toBe($this->reporterType->id)
        ->and($carbone->fresh()->notes)->toBe(implode("\n", [
            'Supervisore commerciale: Antonio Alvoni',
            '10% sul mantenimento ISO SOA E GDPR - FNC 20%-10% - APL 10%',
            'AVV/GAR: 10% dal 01/01/2027.',
        ]))
        ->and($lettini->fresh()->referent_type_id)->toBe($commercialType->id);
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
        ->and(reporterRules($artica))->not->toHaveKey('Finanza Agevolata - FNC');
});

it('matches a reporter without legacy id by name, ignoring case and word order', function () {
    $falco = Referent::factory()->create(['name' => 'FALCO CLAUDIO']);

    $this->seed(QualificaReporterCommissionSeeder::class);

    expect(Referent::query()->where('name', 'Claudio Falco')->exists())->toBeFalse()
        ->and(reporterRules($falco))->toHaveKey('ISO');
});

it('skips a reporter whose name matches more than one referent', function () {
    Referent::factory()->count(2)->create(['name' => 'Claudio Falco']);

    $this->seed(QualificaReporterCommissionSeeder::class);

    expect(Referent::query()->where('name', 'Claudio Falco')->count())->toBe(2)
        ->and(CommissionConfiguration::query()->where('name', 'like', '% - Claudio Falco - %')->exists())->toBeFalse();
});

it('skips a legacy reporter that is not migrated yet instead of creating it', function () {
    $this->seed(QualificaReporterCommissionSeeder::class);

    expect(Referent::query()->where('name', 'Antonio Carbone')->exists())->toBeFalse();
});

// REQUIREMENT CHANGED (user directive 2026-11-03): a re-run now restores the
// catalogue's values instead of keeping a rule edited in the meantime.
it('converges on a re-run: restores edited rules, drops stale and orphaned seeder rules, keeps hand-made ones', function () {
    $carbone = migratedCarbone();
    $this->seed(QualificaReporterCommissionSeeder::class);
    $rules = CommissionConfiguration::query()->count();

    $edited = reporterRules($carbone)['ISO'];
    $edited->update(['value' => '12']);
    $extra = ProductCategory::factory()->create(['name' => 'Altro']);
    $stale = CommissionConfiguration::factory()->create([
        'name' => 'Segnalatore - Antonio Carbone - Altro', 'recipient_role' => CommissionRecipientRole::Reporter,
        'application_scope' => CommissionApplicationScope::ProductCategory, 'product_category_id' => $extra->id, 'product_id' => null,
        'recipient_type' => 'referent', 'recipient_id' => $carbone->id,
    ]);
    $handMade = $stale->replicate()->fill(['name' => 'Accordo speciale Carbone']);
    $handMade->save();
    $applied = reporterRules(Referent::query()->where('name', 'Claudio Falco')->sole())['SOA'];
    QuoteLineCommission::factory()->create(['commission_configuration_id' => $applied->id]);
    Referent::query()->where('name', 'Claudio Falco')->sole()->delete();

    $this->seed(QualificaReporterCommissionSeeder::class);

    expect($edited->fresh()->value)->toBe('20.0000')
        ->and(CommissionConfiguration::query()->find($stale->id))->toBeNull()
        ->and($handMade->fresh())->not->toBeNull()
        ->and($applied->fresh()->status)->toBe(CommissionConfigurationStatus::Suspended)
        ->and(CommissionConfiguration::query()->count())->toBe($rules + 2);
});

it('reads the year a product name marks', function (string $name, ?int $year) {
    expect(ReporterCommissionTargets::marksYear($name, 2))->toBe($year === 2)
        ->and(ReporterCommissionTargets::marksYear($name, 3))->toBe($year === 3);
})->with([
    ['Consulenza ISO 9001_2° Anno', 2],
    ['Ampliamento Scopo ISO 9001_3°Anno', 3],
    ['Consulenza ISO 9001__3° Anno', 3],
    ['Adeguamento Normativo: UNI ISO 37001:2025_II anno', 2],
    ['Aggiornamento Sistema di Gestione Standard conforme alla norma: UNI EN ISO 20121 N.A._III ANNO', 3],
    ['RECAP_Progetti Tecnici di Consulenza_II° Anno', 2],
    ['Transizione ISO 27001:2013 in ISO/IEC 27001:2022_2 anno', 2],
    ['Consulenza ISO 9001_1° Anno', null],
    ['Consulenza ISO 9001+27001__I anno', null],
    ['Corso Responsabile Sistema di Gestione Ambientale', null],
]);
