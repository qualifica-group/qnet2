<?php

declare(strict_types=1);

use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;

if (! function_exists('seedMigrationsConfig')) {
    /**
     * The `migrations.*` config every source-import Feature test relies on:
     * a fake base URL (`fakeMigrationsBaseUrl()`, below), no token,
     * tight timeouts/retries so a failing HTTP double
     * never stalls the suite, and the SAME `import_batch_size` the app's
     * own default already is (App\Migrations\AbstractMigrationSource falls
     * back to 100) — explicit here so the value is asserted, not just
     * inherited.
     *
     * Canonicalised here (engineering.md §1.2): 26 test files declared this
     * behind a `function_exists` guard, three of them without the
     * `import_batch_size` key — a difference with no observable effect
     * given the shared default, but a genuine divergence: whichever copy's
     * file happened to load first decided the value for the WHOLE run.
     */
    function seedMigrationsConfig(): void
    {
        config([
            'migrations.base_url' => fakeMigrationsBaseUrl(),
            'migrations.token' => null,
            'migrations.timeout' => 5,
            'migrations.retry_times' => 1,
            'migrations.retry_sleep_ms' => 1,
            'migrations.import_batch_size' => 100,
        ]);
    }
}

if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('migrationsSuperAdminActor')) {
    /**
     * The super-admin a migration run is launched by. Canonical copy for the
     * spec 0189 sources; the older Migration test files still carry their own
     * guarded copy, skipped because this file is loaded first (tests/Pest.php).
     */
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationService::class));
    }
}
