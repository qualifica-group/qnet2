<?php

namespace App\Support\Import;

use App\Models\City;
use App\Models\Province;
use App\Models\State;
use Illuminate\Validation\Validator;

/**
 * The `geo` PATCH block's own rules (spec 0038, AC-003/AC-004), extracted
 * from UpdateImportRowRequest to keep it under the 300-line soft limit
 * (engineering.md §6): only the 4 known level keys are accepted, and a child
 * id must actually belong to its declared parent — the same hierarchy
 * GeoSelect enforces client-side, so an incoherent pin never reaches
 * StagedRowReviser/GeoPinResolver. Existence (`exists:`) stays a plain rule
 * on the request.
 */
final class GeoPinValidator
{
    /** The only keys `geo` accepts — any other one is rejected (AC-004). */
    private const array GEO_KEYS = ['country_id', 'state_id', 'province_id', 'city_id'];

    public function validate(Validator $validator, mixed $geo): void
    {
        if (! is_array($geo)) {
            return;
        }

        $this->validateKeys($validator, $geo);
        $this->validateHierarchy($validator, $geo);
    }

    /**
     * @param  array<string, mixed>  $geo
     */
    private function validateKeys(Validator $validator, array $geo): void
    {
        foreach (array_keys($geo) as $key) {
            if (! in_array($key, self::GEO_KEYS, true)) {
                $validator->errors()->add("geo.{$key}", "The field [geo.{$key}] is not a recognized geo level.");
            }
        }
    }

    /**
     * A child id is only coherent when its declared parent id resolves to
     * the SAME ancestor the child actually belongs to (spec 0038 AC-003) —
     * the province is an optional level: a city with no province instead
     * agrees directly with its state (mirroring GeoResolver's own
     * province-optional scoping).
     *
     * @param  array<string, mixed>  $geo
     */
    private function validateHierarchy(Validator $validator, array $geo): void
    {
        $countryId = $geo['country_id'] ?? null;
        $stateId = $geo['state_id'] ?? null;
        $provinceId = $geo['province_id'] ?? null;
        $cityId = $geo['city_id'] ?? null;

        if ($stateId !== null) {
            if ($countryId === null) {
                $validator->errors()->add('geo.state_id', 'A state requires a country.');
            } elseif (($state = State::find($stateId)) !== null && $state->country_id !== (int) $countryId) {
                $validator->errors()->add('geo.state_id', 'The selected state does not belong to the given country.');
            }
        }

        if ($provinceId !== null) {
            if ($stateId === null) {
                $validator->errors()->add('geo.province_id', 'A province requires a state.');
            } elseif (($province = Province::find($provinceId)) !== null && $province->state_id !== (int) $stateId) {
                $validator->errors()->add('geo.province_id', 'The selected province does not belong to the given state.');
            }
        }

        if ($cityId === null) {
            return;
        }

        if ($stateId === null) {
            $validator->errors()->add('geo.city_id', 'A city requires a state.');

            return;
        }

        $city = City::find($cityId);

        if ($city === null) {
            return;
        }

        $belongs = $provinceId !== null ? $city->province_id === (int) $provinceId : $city->state_id === (int) $stateId;

        if (! $belongs) {
            $validator->errors()->add('geo.city_id', 'The selected city does not belong to the given province/state.');
        }
    }
}
