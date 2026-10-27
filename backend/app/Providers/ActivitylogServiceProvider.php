<?php

namespace App\Providers;

use App\Providers\Concerns\SkipsMigrationPublishingInTests;
use Spatie\Activitylog\ActivitylogServiceProvider as BaseActivitylogServiceProvider;

/**
 * Replaces the auto-discovered provider (composer.json dont-discover).
 */
class ActivitylogServiceProvider extends BaseActivitylogServiceProvider
{
    use SkipsMigrationPublishingInTests;
}
