<?php

use App\Models\EmploymentContract;
use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-16 10:00:00'));
afterEach(fn () => Carbon::setTestNow());

it('0272 AC-012 deactivates users whose termination arrived and leaves future ones alone', function () {
    $past = EmploymentProfile::factory()->create(['terminated_at' => '2026-10-15'])->user;
    $today = EmploymentProfile::factory()->create(['terminated_at' => '2026-10-16'])->user;
    $future = EmploymentProfile::factory()->create(['terminated_at' => '2026-10-17'])->user;
    $noTermination = EmploymentProfile::factory()->create()->user;
    $alreadyInactive = EmploymentProfile::factory()->create(['terminated_at' => '2026-01-01'])->user;
    $alreadyInactive->update(['is_active' => false]);

    $this->artisan('employment:daily-sync')->assertSuccessful();

    expect($past->fresh()->is_active)->toBeFalse()
        ->and($today->fresh()->is_active)->toBeFalse()
        ->and($future->fresh()->is_active)->toBeTrue()
        ->and($noTermination->fresh()->is_active)->toBeTrue()
        ->and(User::query()->where('is_active', false)->count())->toBe(3);
});

it('0272 AC-012 records the deactivation in the activity log', function () {
    $user = EmploymentProfile::factory()->create(['terminated_at' => '2026-10-16'])->user;

    $this->artisan('employment:daily-sync')->assertSuccessful();

    expect($user->activities()->where('event', 'updated')->exists())->toBeTrue();
});

it('0272 AC-004 AC-012 a future condition becomes the copy once its start date arrives', function () {
    $profile = EmploymentProfile::factory()->create(['relationship_type' => 'employee', 'company_id' => null]);
    EmploymentContract::factory()->create(['employment_profile_id' => $profile->id, 'starts_on' => '2026-01-01', 'relationship_type' => 'employee']);
    EmploymentContract::factory()->create(['employment_profile_id' => $profile->id, 'starts_on' => '2026-11-01', 'relationship_type' => 'self_employed']);

    $this->artisan('employment:daily-sync')->assertSuccessful();
    expect($profile->fresh()->relationship_type->value)->toBe('employee');

    Carbon::setTestNow('2026-11-01 00:30:00');
    $this->artisan('employment:daily-sync')->assertSuccessful();

    expect($profile->fresh()->relationship_type->value)->toBe('self_employed');
});

it('0272 AC-012 leaves profiles without a condition history untouched', function () {
    $profile = EmploymentProfile::factory()->create(['relationship_type' => 'other']);

    $this->artisan('employment:daily-sync')->assertSuccessful();

    expect($profile->fresh()->relationship_type->value)->toBe('other');
});

it('0272 AC-012 is scheduled daily at 00:30 without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'employment:daily-sync'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
