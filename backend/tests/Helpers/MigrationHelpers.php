<?php

declare(strict_types=1);

if (! function_exists('seedMigrationsConfig')) {
    /**
     * The `migrations.*` config every source-import Feature test relies on:
     * a fake base URL (`fakeMigrationsBaseUrl()`, declared locally by each
     * caller), no token, tight timeouts/retries so a failing HTTP double
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
