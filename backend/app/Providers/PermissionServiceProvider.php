<?php

namespace App\Providers;

use App\Providers\Concerns\SkipsMigrationPublishingInTests;
use Spatie\Permission\PermissionServiceProvider as BasePermissionServiceProvider;

/**
 * Replaces the auto-discovered provider (composer.json dont-discover).
 */
class PermissionServiceProvider extends BasePermissionServiceProvider
{
    use SkipsMigrationPublishingInTests;
}
