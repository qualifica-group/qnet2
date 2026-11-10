<?php

namespace App\Providers;

use App\Authorization\IndexedPermissionRegistrar;
use App\Providers\Concerns\SkipsMigrationPublishingInTests;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider as BasePermissionServiceProvider;

/**
 * Replaces the auto-discovered provider (composer.json dont-discover).
 */
class PermissionServiceProvider extends BasePermissionServiceProvider
{
    use SkipsMigrationPublishingInTests;

    public function packageBooted(): void
    {
        parent::packageBooted();

        // The parent binds its own registrar while booting: this one must come after.
        $this->app->singleton(PermissionRegistrar::class, IndexedPermissionRegistrar::class);
    }
}
