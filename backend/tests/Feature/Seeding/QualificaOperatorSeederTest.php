<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\PersonalData;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaOperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

// The client's real operators ("Mansionario Operatori", user directive
// 2026-09-15): account, role, Sedi and competence per roster row. The sites and
// the business functions come from the legacy import, so each test stands in
// the rows that import would have left.
uses(RefreshDatabase::class);

function seededOperator(string $email): User
{
    return User::query()->where('email', $email)->with('employment.operationalSites', 'employment.productLines')->firstOrFail();
}

/**
 * @param  array<int, string>  $aliases
 * @return array<string, int>
 */
function standInSites(array $aliases): array
{
    return collect($aliases)
        ->mapWithKeys(fn (string $alias): array => [$alias => OperationalSite::factory()->create(['alias' => $alias])->id])
        ->all();
}

/**
 * Merged scenario: six of the twelve tests below share the exact same
 * setup — no pre-seeded Sede/ProductCategory, no mutation that could bleed
 * into another test's read — so instead of paying QualificaOperatorSeeder
 * once (or twice, for the re-run checks) per test, it runs twice total for
 * all six. The other six build a distinct pre-state (different Sedi/
 * categories) or mutate the seeded data before a re-run, so they stay
 * separate rather than risk one test's fixture bleeding into another's.
 */
it('seeds every roster account, its role and its anagrafica, and converges on a re-run', function (): void {
    // Step 1: seed once — every single-run check below reads this state.
    test()->seed(QualificaOperatorSeeder::class);

    // was: 'creates every roster account with its role, standalone and without imported data'
    foreach (OperatorRoster::OPERATORS as [, , $email, , $role]) {
        expect(User::query()->where('email', $email)->firstOrFail()->getRoleNames()->all())->toBe([$role]);
    }

    expect(User::query()->count())->toBe(count(OperatorRoster::OPERATORS));

    // was: 'never seeds the accounts highlighted as non-existent'
    expect(User::query()->whereIn('name', ['Miriam Del Giudice', 'Maddalena Vitale', 'Elisa Finizio', 'Imma Pascale'])->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(72);

    // was: 'grants every commercial their own and physical-Sede enrollees, and the teaching supervisor both modules by Sede'
    $commercial = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();
    $formerEnrollee = User::query()->where('email', 'marco.fedele@qualificagroup.com')->firstOrFail();

    expect($formerEnrollee->getRoleNames()->all())->toBe(['commerciale']);

    foreach ([$commercial, $formerEnrollee] as $user) {
        foreach (['viewAny', 'view'] as $ability) {
            expect($user->can("enrollee-management.{$ability}"))->toBeTrue("{$user->email}: {$ability}");
        }

        foreach (['update', 'viewAll', 'export'] as $ability) {
            expect($user->can("enrollee-management.{$ability}"))->toBeFalse("{$user->email}: {$ability}");
        }

        expect($user->can('request-management.viewSite'))->toBeFalse()
            ->and($user->can('request-statistics.view'))->toBeFalse()
            ->and($user->can('enrollee-management.viewSite'))->toBeFalse("{$user->email}: viewSite")
            ->and($user->can('enrollee-management.viewPrimarySite'))->toBeTrue("{$user->email}: viewPrimarySite");
    }

    $teaching = seededOperator('marlena.jaruga@qualificagroup.com');

    expect($teaching->employment->productLines)->toBeEmpty()
        ->and($teaching->can('request-management.viewSite'))->toBeTrue()
        ->and($teaching->can('request-management.viewAll'))->toBeFalse()
        ->and($teaching->can('request-statistics.view'))->toBeFalse()
        ->and($teaching->can('enrollee-management.viewSite'))->toBeTrue();

    // was: 'gives the coordinator unrestricted requests without the field-change-requests page'
    $coordinator = User::query()->where('email', 'michela.fabozzi@qualificagroup.com')->firstOrFail();

    expect($coordinator->can('request-management.viewAll'))->toBeTrue()
        ->and($coordinator->can('request-statistics.view'))->toBeTrue()
        ->and($coordinator->can('leads.viewAny'))->toBeTrue()
        ->and($coordinator->can('field-change-requests.view'))->toBeFalse()
        ->and($coordinator->can('field-change-requests.manage'))->toBeFalse();

    // was: 'sets the shared password on creation only, so an operator change survives a re-run'
    // First half: the shared password holds right after the first seed.
    $passwordUser = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();
    expect(Hash::check('Qualifica2026!', $passwordUser->password))->toBeTrue();

    $passwordUser->forceFill(['password' => Hash::make('changed-by-hand')])->save();

    // Step 2: re-seed — natural key, no duplicates; a hand-made change to a
    // field the seeder only sets on creation must survive the re-run.
    test()->seed(QualificaOperatorSeeder::class);

    // was: 'writes first and last name onto the anagrafica too, converging on a re-run'
    $anagraficaUser = User::query()->where('email', 'mariaclelia.bernardi@qualificagroup.com')->with('personalData')->sole();

    expect($anagraficaUser->name)->toBe('Maria Clelia Bernardi')
        ->and($anagraficaUser->personalData->type)->toBe(PersonalDataTypeEnum::Individual)
        ->and($anagraficaUser->personalData->first_name)->toBe('Maria Clelia')
        ->and($anagraficaUser->personalData->last_name)->toBe('Bernardi')
        ->and(PersonalData::query()->count())->toBe(count(OperatorRoster::OPERATORS));

    // was: 'sets the shared password on creation only...' (second half)
    expect(Hash::check('changed-by-hand', $passwordUser->fresh()->password))->toBeTrue()
        ->and(User::query()->where('email', 'marco.baldi@qualificagroup.com')->count())->toBe(1);
});

it('expands each city to every site of it: first one physical, the rest remote', function (): void {
    $sites = standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma', 'Roma - 1', 'Milano', 'Pero', 'Cassino', 'Ragusa']);

    test()->seed(QualificaOperatorSeeder::class);

    $employment = seededOperator('gaetano.dellaporta@qualificagroup.com')->employment;

    expect($employment->primaryOperationalSiteId)->toBe($sites['FRATTAMAGGIORE 1 (HQ)'])
        ->and($employment->remoteOperationalSiteIds)->toEqualCanonicalizing([
            $sites['Frattamaggiore 2'], $sites['Roma'], $sites['Roma - 1'], $sites['Milano'], $sites['Cassino'],
        ]);
});

it('leaves a "no operatore" profile unassignable: physical Sede only, no competence, even when seeded as a wildcard before', function (): void {
    $sites = standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma']);
    $function = BusinessFunction::factory()->create();
    ProductCategory::factory()->create(['name' => 'Autoimpiego', 'business_function_id' => $function->id]);

    test()->seed(QualificaOperatorSeeder::class);
    seededOperator('rosa.falzarano@qualificagroup.com')->employment->update(['covers_all_product_categories' => true]);
    test()->seed(QualificaOperatorSeeder::class);

    $employment = seededOperator('rosa.falzarano@qualificagroup.com')->employment;

    expect($employment->primaryOperationalSiteId)->toBe($sites['FRATTAMAGGIORE 1 (HQ)'])
        ->and($employment->remoteOperationalSiteIds)->toBeEmpty()
        ->and($employment->covers_all_product_categories)->toBeFalse()
        ->and($employment->productLines)->toBeEmpty();
});

// User directive 2026-10-05: four supervisors mirror Rosa Falzarano but are
// competent for every category of the APL function (spec 0194 D-4, requirement
// changed from the APL category row); Martina Mosca mirrors Michela Fabozzi.
it('seeds the APL supervisors like Rosa Falzarano, competent for APL, and Martina Mosca like Michela Fabozzi', function (): void {
    $sites = standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2']);
    $function = BusinessFunction::factory()->create(['name' => 'APL']);

    test()->seed(QualificaOperatorSeeder::class);

    $reference = seededOperator('rosa.falzarano@qualificagroup.com');

    foreach (['giovanna.gervasio', 'raffaele.distico', 'gessica.crispo', 'emanuele.ascione'] as $name) {
        $supervisor = seededOperator("{$name}@qualificagroup.com");
        $employment = $supervisor->employment;

        expect($supervisor->getRoleNames()->all())->toBe($reference->getRoleNames()->all())
            ->and($employment->job_description)->toBe($reference->employment->job_description)
            ->and($employment->primaryOperationalSiteId)->toBe($sites['FRATTAMAGGIORE 1 (HQ)'])
            ->and($employment->remoteOperationalSiteIds)->toBeEmpty()
            ->and($employment->productLines->map(fn ($line): array => [$line->business_function_id, $line->product_category_id])->all())
            ->toBe([[$function->id, null]])
            ->and($employment->is_assignable)->toBeTrue();
    }

    $coordinator = seededOperator('michela.fabozzi@qualificagroup.com');
    $mosca = seededOperator('martina.mosca@qualificagroup.com');

    expect($mosca->getRoleNames()->all())->toBe($coordinator->getRoleNames()->all())
        ->and($mosca->employment->job_description)->toBe($coordinator->employment->job_description)
        ->and($mosca->employment->primaryOperationalSiteId)->toBe($sites['FRATTAMAGGIORE 1 (HQ)'])
        ->and($mosca->employment->remoteOperationalSiteIds)->toBeEmpty()
        ->and($mosca->employment->productLines)->toBeEmpty();
});

it('pairs every roster category with its effective business function, Consulenza never', function (): void {
    $formazione = BusinessFunction::factory()->create(['name' => 'FORMAZIONE']);
    $apl = BusinessFunction::factory()->create(['name' => 'APL']);
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'business_function_id' => $formazione->id]);
    $gol = ProductCategory::factory()->childOf($root)->create(['name' => 'GOL - Campania']);
    $selfFunded = ProductCategory::factory()->childOf($root)->create(['name' => 'Autofinanziato']);
    $selfEmployment = ProductCategory::factory()->childOf($root)->create(['name' => 'Autoimpiego']);
    $aplRoot = ProductCategory::factory()->create(['name' => 'APL', 'business_function_id' => $apl->id]);
    ProductCategory::factory()->create(['name' => 'Consulenza']);

    test()->seed(QualificaOperatorSeeder::class);

    $lines = seededOperator('luca.romano@qualificagroup.com')->employment->productLines
        ->map(fn ($line): array => [$line->business_function_id, $line->product_category_id])
        ->all();

    expect($lines)->toEqualCanonicalizing([
        [$formazione->id, $gol->id],
        [$formazione->id, $selfFunded->id],
        [$formazione->id, $selfEmployment->id],
        [$apl->id, $aplRoot->id],
    ]);

    // A Consulenza-only commercial has no roster category: since spec 0194
    // (D-4, requirement changed) they are switched off with every FORMAZIONE
    // category instead of no competence row at all.
    $baldi = seededOperator('marco.baldi@qualificagroup.com')->employment;

    expect($baldi->is_assignable)->toBeFalse()
        ->and($baldi->productLines->map(fn ($line): array => [$line->business_function_id, $line->product_category_id])->all())
        ->toBe([[$formazione->id, null]]);
});

it('converges on a re-run: no duplicated memberships nor competence rows', function (): void {
    standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma', 'Milano', 'Cassino']);
    $function = BusinessFunction::factory()->create();
    ProductCategory::factory()->create(['name' => 'Autoimpiego', 'business_function_id' => $function->id]);

    test()->seed(QualificaOperatorSeeder::class);
    test()->seed(QualificaOperatorSeeder::class);

    $employment = seededOperator('antonio.alvoni@qualificagroup.com')->employment;

    expect($employment->operationalSites)->toHaveCount(2)
        ->and($employment->productLines)->toHaveCount(1);
});

// User directive 2026-09-18: Gestione Iscritti is read-only for every
// commercial role. Spec 0165 D-5: the commercial reaches the offers they
// operate plus the enrollees of their PHYSICAL Sede, and the former
// "commerciale-iscritti" (Marco Fedele) is merged into the commercial role.
it('lists in Gestione Iscritti the own and physical-Sede rows of the commercials, every Sede of the teaching supervisor', function (): void {
    $sites = standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma']);
    test()->seed(QualificaOperatorSeeder::class);

    // Marco Baldi and Marco Fedele (both commerciale since spec 0165) are
    // physically in Frattamaggiore; Roma is only a REMOTE Sede for Fedele and
    // the physical one of Marlena Jaruga (viewSite).
    $commercial = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();
    $formerEnrollee = User::query()->where('email', 'marco.fedele@qualificagroup.com')->firstOrFail();
    $teaching = User::query()->where('email', 'marlena.jaruga@qualificagroup.com')->firstOrFail();
    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();

    $validated = QuoteWorkflowStatus::factory()->global()->system('validated')->create()->id;
    $enrolleeQuote = fn (array $attributes): Quote => Quote::factory()->create([...$attributes, 'quote_workflow_status_id' => $validated]);

    $commercialOwn = $enrolleeQuote(['operator_id' => $commercial->id]);
    $frattamaggiore = $enrolleeQuote(['operational_site_id' => $sites['FRATTAMAGGIORE 1 (HQ)']]);
    $roma = $enrolleeQuote(['operational_site_id' => $sites['Roma']]);
    $elsewhere = $enrolleeQuote([]);

    $rowIds = function (User $user): array {
        Sanctum::actingAs($user);

        return collect(test()->postJson('/api/tables/enrollee-management/rows', ['startRow' => 0, 'endRow' => 25])
            ->assertOk()
            ->json('items'))->pluck('id')->sort()->values()->all();
    };
    $sorted = fn (Quote ...$quotes): array => collect($quotes)->pluck('id')->sort()->values()->all();

    expect($rowIds($commercial))->toBe($sorted($commercialOwn, $frattamaggiore))
        ->and($rowIds($formerEnrollee))->toBe($sorted($frattamaggiore))
        ->and($rowIds($teaching))->toBe($sorted($roma))
        ->and($rowIds($supervisor))->toBe($sorted($commercialOwn, $frattamaggiore, $roma, $elsewhere));
});

it('deletes the retired roles, detaching whoever still held them', function (): void {
    $retired = Role::findOrCreate('supervisor', 'web');
    Role::findOrCreate('commercial', 'web');
    Role::findOrCreate('commerciale-iscritti', 'web'); // spec 0165 D-5, merged into commerciale
    $formerTester = User::factory()->create();
    $formerTester->assignRole($retired);

    test()->seed(QualificaOperatorSeeder::class);
    test()->seed(QualificaOperatorSeeder::class);

    expect(Role::query()->whereIn('name', ['supervisor', 'commercial', 'commerciale-iscritti'])->exists())->toBeFalse()
        ->and(Role::query()->where('name', 'marketing')->exists())->toBeTrue()
        ->and($formerTester->fresh()->getRoleNames()->all())->toBe([]);
});
