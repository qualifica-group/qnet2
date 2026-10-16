<?php

use App\Enums\RelationshipTypeEnum;
use App\Enums\SalaryPeriodEnum;
use App\Models\CollectiveAgreement;
use App\Models\CollectiveAgreementLevel;
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

it('orders the levels of an agreement and links them to contracts', function () {
    $agreement = CollectiveAgreement::factory()->create();
    $second = CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create(['code' => 'B', 'sort_order' => 2]);
    $first = CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create(['code' => 'A', 'sort_order' => 1]);
    $contract = EmploymentContract::factory()->create(['collective_agreement_level_id' => $first->id]);

    expect($agreement->levels->pluck('code')->all())->toBe(['A', 'B'])
        ->and($first->agreement->is($agreement))->toBeTrue()
        ->and($first->contracts->pluck('id')->all())->toBe([$contract->id])
        ->and($contract->collectiveAgreementLevel->is($first))->toBeTrue()
        ->and($second->contracts)->toHaveCount(0);
});

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

it('enforces unique names, level codes per agreement and start dates per profile', function () {
    CollectiveAgreement::factory()->create(['name' => 'Commercio']);
    $agreement = CollectiveAgreement::factory()->create();
    CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create(['code' => 'Q1']);
    $profile = EmploymentProfile::factory()->create();
    EmploymentContract::factory()->for($profile, 'profile')->create(['starts_on' => '2022-05-01']);

    expect(fn () => CollectiveAgreement::factory()->create(['name' => 'Commercio']))->toThrow(QueryException::class)
        ->and(fn () => CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create(['code' => 'Q1']))->toThrow(QueryException::class)
        ->and(CollectiveAgreementLevel::factory()->create(['code' => 'Q1']))->toBeInstanceOf(CollectiveAgreementLevel::class)
        ->and(fn () => EmploymentContract::factory()->for($profile, 'profile')->create(['starts_on' => '2022-05-01']))->toThrow(QueryException::class);
});

it('restricts deleting a level or a company referenced by a contract', function () {
    $level = CollectiveAgreementLevel::factory()->create();
    $company = Company::factory()->create();
    EmploymentContract::factory()->create([
        'collective_agreement_level_id' => $level->id,
        'company_id' => $company->id,
    ]);

    expect(fn () => $level->delete())->toThrow(QueryException::class)
        ->and(fn () => $company->delete())->toThrow(QueryException::class);
});

it('cascades levels with their agreement and contracts with their profile', function () {
    $agreement = CollectiveAgreement::factory()->create();
    CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create();
    $contract = EmploymentContract::factory()->create();

    $agreement->delete();
    $contract->profile->delete();

    expect(CollectiveAgreementLevel::count())->toBe(0)
        ->and(EmploymentContract::count())->toBe(0);
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

it('registers the morph aliases of the new models', function () {
    expect(Relation::getMorphedModel('collective_agreement'))->toBe(CollectiveAgreement::class)
        ->and(Relation::getMorphedModel('collective_agreement_level'))->toBe(CollectiveAgreementLevel::class)
        ->and(Relation::getMorphedModel('employment_contract'))->toBe(EmploymentContract::class);
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
