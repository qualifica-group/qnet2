<?php

use App\Models\EmploymentProfile;
use App\Services\TimeEntries\DailyTargetResolver;

/*
|--------------------------------------------------------------------------
| DailyTargetResolver (spec 0122, D-7, AC-011)
|--------------------------------------------------------------------------
| Pure: builds EmploymentProfile instances in memory (never persisted), so
| this needs no database. The no-profile/null-standard fallback branches
| call config(), which needs the Laravel container Pest only boots for
| `Feature` tests (tests/Pest.php) — those two branches are exercised there
| instead (TimeEntryListTest "AC-011: without a profile...").
*/

it('AC-011: standard 510 minus break 30 resolves to 480', function () {
    $resolver = new DailyTargetResolver;
    $profile = new EmploymentProfile(['standard_daily_minutes' => 510, 'break_daily_minutes' => 30]);

    expect($resolver->resolve($profile))->toBe(480);
});

it('a null break is treated as zero', function () {
    $resolver = new DailyTargetResolver;
    $profile = new EmploymentProfile(['standard_daily_minutes' => 450, 'break_daily_minutes' => null]);

    expect($resolver->resolve($profile))->toBe(450);
});

it('never goes negative: a break larger than the standard clamps to 0', function () {
    $resolver = new DailyTargetResolver;
    $profile = new EmploymentProfile(['standard_daily_minutes' => 120, 'break_daily_minutes' => 200]);

    expect($resolver->resolve($profile))->toBe(0);
});
