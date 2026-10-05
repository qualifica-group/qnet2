<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\QualificaCatalog\StaffRoster;
use Database\Seeders\QualificaOperatorSeeder;
use Database\Seeders\QualificaStaffSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Spec 0194 D-4/D-5 (user directive 2026-10-05): the production seed decides
 * each real account's "Assegnabile" switch and, for the operators, the
 * competence that goes with it.
 */
if (! function_exists('switchSeedProfileOf')) {
    function switchSeedProfileOf(string $email): EmploymentProfile
    {
        return User::query()->where('email', $email)->sole()->employment()->with('productLines')->sole();
    }
}

if (! function_exists('switchSeedLinesOf')) {
    /**
     * @return array<int, array{0: int, 1: int|null}>
     */
    function switchSeedLinesOf(EmploymentProfile $profile): array
    {
        return $profile->productLines
            ->map(fn ($line): array => [(int) $line->business_function_id, $line->product_category_id === null ? null : (int) $line->product_category_id])
            ->all();
    }
}

it('D-4: switches the operators on or off and gives the switched-off ones every FORMAZIONE category', function (): void {
    // Upper case, like the imported functions: the roster's "Formazione" must still match.
    $formazione = BusinessFunction::factory()->create(['name' => 'FORMAZIONE']);
    $apl = BusinessFunction::factory()->create(['name' => 'APL']);
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'business_function_id' => $formazione->id]);
    $selfEmployment = ProductCategory::factory()->childOf($root)->create(['name' => 'Autoimpiego']);

    test()->seed(QualificaOperatorSeeder::class);
    test()->seed(QualificaOperatorSeeder::class);

    foreach (['giovanna.gervasio', 'raffaele.distico', 'gessica.crispo', 'emanuele.ascione'] as $name) {
        $profile = switchSeedProfileOf("{$name}@qualificagroup.com");

        expect($profile->is_assignable)->toBeTrue()
            ->and(switchSeedLinesOf($profile))->toBe([[$apl->id, null]]);
    }

    foreach (['michela.fabozzi', 'rosa.falzarano', 'marlena.jaruga', 'marco.baldi'] as $name) {
        $profile = switchSeedProfileOf("{$name}@qualificagroup.com");

        expect($profile->is_assignable)->toBeFalse()
            ->and(switchSeedLinesOf($profile))->toBe([[$formazione->id, null]]);
    }

    $alvoni = switchSeedProfileOf('antonio.alvoni@qualificagroup.com');

    expect($alvoni->is_assignable)->toBeTrue()
        ->and(switchSeedLinesOf($alvoni))->toBe([[$formazione->id, $selfEmployment->id]]);
});

it('D-4: re-switches on a re-run an operator changed by hand', function (): void {
    BusinessFunction::factory()->create(['name' => 'FORMAZIONE']);
    test()->seed(QualificaOperatorSeeder::class);
    switchSeedProfileOf('michela.fabozzi@qualificagroup.com')->update(['is_assignable' => true]);

    test()->seed(QualificaOperatorSeeder::class);

    expect(switchSeedProfileOf('michela.fabozzi@qualificagroup.com')->is_assignable)->toBeFalse();
});

it('D-5: switches off the named accounts and the staff, with no competence', function (): void {
    [, , $existingWithProfile] = StaffRoster::USERS[0];
    [, , $existingWithoutProfile] = StaffRoster::USERS[1];
    [, , $created] = StaffRoster::USERS[2];
    EmploymentProfile::factory()->for(User::factory()->create(['email' => $existingWithProfile]))->create();
    User::factory()->create(['email' => $existingWithoutProfile]);

    test()->seed(TestUsersSeeder::class);
    test()->seed(QualificaStaffSeeder::class);

    $ciro = switchSeedProfileOf('ciro.cacciapuoti@qualificagroup.com');

    expect($ciro->is_assignable)->toBeFalse()
        ->and($ciro->productLines)->toBeEmpty()
        ->and(switchSeedProfileOf($created)->is_assignable)->toBeFalse()
        ->and(switchSeedProfileOf($existingWithoutProfile)->is_assignable)->toBeFalse()
        // Create-only: a profile that already exists is never touched.
        ->and(switchSeedProfileOf($existingWithProfile)->is_assignable)->toBeTrue();
});
