<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec 0120, D-8: the FIRST scheduled command in this repo. Materializes the
// Task occurrences due for every recurrence series (App\Console\Commands\
// GenerateTaskRecurrences). `withoutOverlapping()` guards a slow run against
// a second one starting before it finishes; the command itself is idempotent
// regardless (D-9), so an overlap would be wasteful, never unsafe. Running
// this at all requires the system cron `php artisan schedule:run` every
// minute, which is infrastructure outside this repo (AC-030, a manual,
// operator-side verification).
Schedule::command('tasks:generate-recurrences')->dailyAt('01:00')->withoutOverlapping();
