<?php

use App\Enums\RelationshipTypeEnum;
use App\Enums\SalaryPeriodEnum;
use App\Models\Company;
use App\Models\Contact;
use App\Models\EmploymentContract;
use App\Models\EmploymentProfile;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

const BACKFILL_MIGRATION = '2026_10_21_100500_backfill_employment_contracts_from_profiles';
const DROP_QUALIFICATION_MIGRATION = '2026_10_21_100600_drop_qualification_type_from_employment_profiles_table';

it('casts the contract attributes and lists contracts latest first', function () {
    $profile = EmploymentProfile::factory()->create();
    EmploymentContract::factory()->for($profile, 'profile')->create(['starts_on' => '2020-01-01']);
    $latest = EmploymentContract::factory()->for($profile, 'profile')->withSalary('1999.5', SalaryPeriodEnum::Yearly)
        ->create(['starts_on' => '2024-03-01']);

    $fresh = $latest->fresh();

    expect($profile->contracts->first()->is($latest))->toBeTrue()
        ->and($fresh->starts_on->format('Y-m-d'))->toBe('2024-03-01')
        ->and($fresh->relationship_type)->toBe(RelationshipTypeEnum::Employee)
        ->and($fresh->salary_amount)->toBe('1999.50')
        ->and($fresh->salary_period)->toBe(SalaryPeriodEnum::Yearly)
        ->and($fresh->profile->is($profile))->toBeTrue();
});

// REQUIREMENT CHANGED (spec 0293): the CCNL catalogue and its level codes are gone.
it('enforces unique start dates per profile', function () {
    $profile = EmploymentProfile::factory()->create();
    EmploymentContract::factory()->for($profile, 'profile')->create(['starts_on' => '2022-05-01']);

    expect(fn () => EmploymentContract::factory()->for($profile, 'profile')->create(['starts_on' => '2022-05-01']))->toThrow(QueryException::class);
});

// REQUIREMENT CHANGED (spec 0293): no CCNL level references a contract any more.
it('restricts deleting a company referenced by a contract', function () {
    $company = Company::factory()->create();
    EmploymentContract::factory()->create(['company_id' => $company->id]);

    expect(fn () => $company->delete())->toThrow(QueryException::class);
});

it('cascades contracts with their profile', function () {
    $contract = EmploymentContract::factory()->create();

    $contract->profile->delete();

    expect(EmploymentContract::count())->toBe(0);
});

// REQUIREMENT CHANGED (spec 0272 rev.3): the holder joins the IBAN outside the activity log.
it('stores the iban encrypted and keeps it and its holder out of the activity log', function () {
    $profile = EmploymentProfile::factory()->create();
    $profile->update(['iban' => 'IT60X0542811101000000123456', 'iban_holder' => 'Mario Rossi']);

    $raw = DB::table('employment_profiles')->where('id', $profile->id)->value('iban');
    $logged = Activity::query()->where('subject_type', $profile->getMorphClass())->where('subject_id', $profile->id)
        ->get()->map(fn (Activity $activity) => json_encode($activity->properties))->implode('|');

    expect($raw)->not->toBe('IT60X0542811101000000123456')
        ->and($raw)->not->toContain('IT60X')
        ->and($profile->fresh()->iban)->toBe('IT60X0542811101000000123456')
        ->and($profile->fresh()->toArray())->not->toHaveKey('iban')
        ->and($logged)->not->toContain('Mario Rossi')
        ->and($logged)->not->toContain('IT60')
        ->and($logged)->not->toContain('"iban"');
});

it('keeps the salary out of the activity log and of serialization', function () {
    $contract = EmploymentContract::factory()->withSalary('3100.00')->create();
    $logged = Activity::query()->where('subject_type', $contract->getMorphClass())->where('subject_id', $contract->id)
        ->get()->map(fn (Activity $activity) => json_encode($activity->properties))->implode('|');

    expect($contract->fresh()->toArray())->not->toHaveKeys(['salary_amount', 'salary_period'])
        ->and($logged)->not->toContain('salary')
        ->and($logged)->not->toContain('3100');
});

it('defaults the contact work flag to false and casts it', function () {
    $default = Contact::factory()->create();
    $work = Contact::factory()->create(['is_work' => true]);

    expect($default->fresh()->is_work)->toBeFalse()
        ->and($work->fresh()->is_work)->toBeTrue();
});

// REQUIREMENT CHANGED (spec 0293): the CCNL aliases are gone.
it('registers the morph alias of the contract model', function () {
    expect(Relation::getMorphedModel('employment_contract'))->toBe(EmploymentContract::class)
        ->and(Relation::getMorphedModel('collective_agreement'))->toBeNull();
});

it('backfills a first condition from the profile and removes it on rollback (AC-010)', function () {
    $withHire = EmploymentProfile::factory()->create([
        'relationship_type' => 'self_employed', 'hired_at' => '2021-02-03', 'company_id' => null,
    ]);
    $companyOnly = EmploymentProfile::factory()->create([
        'relationship_type' => null, 'hired_at' => null, 'company_id' => Company::factory()->create()->id,
    ]);
    $companyOnly->forceFill(['created_at' => '2023-07-09 10:00:00'])->saveQuietly();
    $empty = EmploymentProfile::factory()->create(['relationship_type' => null, 'company_id' => null]);
    $manual = EmploymentContract::factory()->create(['starts_on' => '2019-01-01']);

    $migration = require database_path('migrations/'.BACKFILL_MIGRATION.'.php');
    $migration->up();

    $first = EmploymentContract::where('employment_profile_id', $withHire->id)->sole();
    $second = EmploymentContract::where('employment_profile_id', $companyOnly->id)->sole();

    expect($first->starts_on->format('Y-m-d'))->toBe('2021-02-03')
        ->and($first->relationship_type)->toBe(RelationshipTypeEnum::SelfEmployed)
        ->and($second->starts_on->format('Y-m-d'))->toBe('2023-07-09')
        ->and($second->relationship_type)->toBe(RelationshipTypeEnum::Other)
        ->and($second->company_id)->toBe($companyOnly->company_id)
        ->and(EmploymentContract::where('employment_profile_id', $empty->id)->exists())->toBeFalse();

    $migration->down();

    expect(EmploymentContract::pluck('id')->all())->toBe([$manual->id]);
});

it('drops and restores the qualification type column', function () {
    $migration = require database_path('migrations/'.DROP_QUALIFICATION_MIGRATION.'.php');

    expect(Schema::hasColumn('employment_profiles', 'qualification_type'))->toBeFalse();

    $migration->down();
    expect(Schema::hasColumn('employment_profiles', 'qualification_type'))->toBeTrue();

    $migration->up();
    expect(Schema::hasColumn('employment_profiles', 'qualification_type'))->toBeFalse();
});
