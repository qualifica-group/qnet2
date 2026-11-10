<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\DataObjects\Users\ProfileData;
use App\Migrations\Sources\Concerns\MapsLegacyRegistryProfile;
use App\Migrations\Support\MigrationGeoResolver;

/**
 * The profile of a legacy `companies` record, mapped exactly as the import maps it.
 */
final class RegistryLegacyProfile
{
    use MapsLegacyRegistryProfile;

    public function __construct(private readonly MigrationGeoResolver $geoResolver) {}

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     */
    public function profile(array $legacy, array &$warnings): ProfileData
    {
        return $this->buildProfile($legacy, $warnings);
    }
}
