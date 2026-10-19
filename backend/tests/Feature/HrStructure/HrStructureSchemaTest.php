<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\JobActivity;
use App\Models\JobRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('links business functions to companies', function () {
    $companies = Company::factory()->count(2)->create();
    $function = BusinessFunction::factory()->create();
    $function->companies()->attach($companies->pluck('id'));

    expect($function->companies()->count())->toBe(2);
});

it('links job roles to activities and employment profiles', function () {
    $role = JobRole::factory()->create();
    $activities = JobActivity::factory()->count(2)->create();
    $role->jobActivities()->attach($activities->pluck('id'));
    $profile = EmploymentProfile::factory()->create();
    $profile->jobRoles()->attach($role);

    expect($role->jobActivities()->count())->toBe(2)
        ->and($activities->first()->jobRoles->pluck('id')->all())->toBe([$role->id])
        ->and($role->employmentProfiles->pluck('id')->all())->toBe([$profile->id])
        ->and($profile->jobRoles->pluck('id')->all())->toBe([$role->id]);
});

it('enforces unique names on the two catalogues', function () {
    JobRole::factory()->create(['name' => 'Buyer']);
    JobActivity::factory()->create(['name' => 'Invoicing']);

    expect(fn () => JobRole::factory()->create(['name' => 'Buyer']))->toThrow(QueryException::class)
        ->and(fn () => JobActivity::factory()->create(['name' => 'Invoicing']))->toThrow(QueryException::class);
});

it('enforces unique pairs on the pivots', function () {
    $role = JobRole::factory()->create();
    $activity = JobActivity::factory()->create();
    $role->jobActivities()->attach($activity);

    expect(fn () => $role->jobActivities()->attach($activity))->toThrow(QueryException::class);
});

it('restricts deleting a job role or an activity that is still referenced', function () {
    $role = JobRole::factory()->create();
    EmploymentProfile::factory()->create()->jobRoles()->attach($role);

    $activity = JobActivity::factory()->create();
    JobRole::factory()->create()->jobActivities()->attach($activity);

    expect(fn () => $role->delete())->toThrow(QueryException::class)
        ->and(fn () => $activity->delete())->toThrow(QueryException::class);
});

it('cascades pivot rows when the owning side is deleted', function () {
    $function = BusinessFunction::factory()->create();
    $function->companies()->attach(Company::factory()->create());
    $role = JobRole::factory()->create();
    $role->jobActivities()->attach(JobActivity::factory()->create());

    $function->delete();
    $role->delete();

    expect(DB::table('business_function_company')->count())->toBe(0)
        ->and(DB::table('job_activity_job_role')->count())->toBe(0);
});

it('rolls the two migrations back and forward again', function () {
    $files = [
        '2026_10_20_100200_create_business_function_company_table',
        '2026_10_20_100100_create_job_roles_and_activities_tables',
    ];

    foreach ($files as $file) {
        (require database_path("migrations/{$file}.php"))->down();
    }

    expect(Schema::hasTable('job_roles'))->toBeFalse()
        ->and(Schema::hasTable('business_function_company'))->toBeFalse();

    foreach (array_reverse($files) as $file) {
        (require database_path("migrations/{$file}.php"))->up();
    }

    expect(Schema::hasTable('employment_profile_job_role'))->toBeTrue()
        ->and(Schema::hasTable('business_function_company'))->toBeTrue();
});
