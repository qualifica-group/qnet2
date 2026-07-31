<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Database\Seeders\QualificaOperatorSiteLinkSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The TestUsersSeeder accounts are given an operational site through their
// employment profile — the column the Operatore select filters on (spec 0048).
// The sites come from the external qnet CRM, so the link runs after the import
// and is never fatal when no site exists.
uses(RefreshDatabase::class);

$emails = fn (): array => array_column(TestUsersSeeder::TEST_USERS, 'email');

it('leaves the accounts unassigned, without failing, when no site was imported', function () use ($emails): void {
    test()->seed(TestUsersSeeder::class);

    test()->seed(QualificaOperatorSiteLinkSeeder::class);

    expect(OperationalSite::query()->count())->toBe(0)
        ->and(EmploymentProfile::query()->count())->toBe(0)
        ->and(User::query()->whereIn('email', $emails())->count())->toBe(count($emails()));
});

it('assigns every test account to the imported site, idempotently', function () use ($emails): void {
    test()->seed(TestUsersSeeder::class);
    $site = OperationalSite::factory()->create();

    test()->seed(QualificaOperatorSiteLinkSeeder::class);
    test()->seed(QualificaOperatorSiteLinkSeeder::class); // re-run: already linked, no second profile.

    $profiles = User::query()->whereIn('email', $emails())->with('employment')->get();

    expect($profiles)->toHaveCount(count($emails()))
        ->and($profiles->pluck('employment.operational_site_id')->unique()->all())->toBe([$site->getKey()])
        ->and(EmploymentProfile::query()->count())->toBe(count($emails()));
});

it('picks the lowest id, never an alias: the legacy catalogue is not ours to pin', function (): void {
    test()->seed(TestUsersSeeder::class);
    $first = OperationalSite::factory()->create(['alias' => 'ZZZ - NON RILEVANTE']);
    OperationalSite::factory()->create(['alias' => 'FRATTAMAGGIORE 1 (HQ)']);

    test()->seed(QualificaOperatorSiteLinkSeeder::class);

    expect(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail()->employment->operational_site_id)
        ->toBe($first->getKey());
});

it('never steals a site already assigned by hand, nor wipes the rest of the profile', function (): void {
    test()->seed(TestUsersSeeder::class);
    OperationalSite::factory()->create();
    $manual = OperationalSite::factory()->create();

    $user = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();
    $user->employment()->create([
        'operational_site_id' => $manual->getKey(),
        'job_description' => 'Supervisore',
    ]);

    test()->seed(QualificaOperatorSiteLinkSeeder::class);

    $employment = $user->employment()->firstOrFail();

    expect($employment->operational_site_id)->toBe($manual->getKey())
        ->and($employment->job_description)->toBe('Supervisore');
});

it('touches only the accounts the seeder owns', function (): void {
    test()->seed(TestUsersSeeder::class);
    OperationalSite::factory()->create();
    $outsider = User::factory()->create();

    test()->seed(QualificaOperatorSiteLinkSeeder::class);

    expect($outsider->employment()->exists())->toBeFalse();
});
