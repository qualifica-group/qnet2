<?php

namespace App\Providers\Concerns;

use ReflectionClass;

/**
 * For spatie package providers re-registered in the app. Registering a
 * migration for publishing globs database/migrations (350+ files) once per
 * migration, and the app boots again for every test: about 25 ms per test
 * spent on a publish tag the suite never uses.
 */
trait SkipsMigrationPublishingInTests
{
    protected function bootPackageMigrations(): self
    {
        return $this->app->runningUnitTests() ? $this : parent::bootPackageMigrations();
    }

    /**
     * package-tools resolves config and migrations relative to the provider's
     * own file: point it back at the vendor package, not app/Providers.
     */
    protected function getPackageBaseDir(): string
    {
        return dirname((new ReflectionClass(get_parent_class($this)))->getFileName());
    }
}
