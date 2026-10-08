<?php

declare(strict_types=1);

namespace App\Tables\Registries;

use App\Http\Requests\Registries\UpdateRegistryRequest;
use App\Models\Registry;
use App\Models\User;
use App\Services\RegistryService;
use App\Services\Table\FormRequestCellValidator;
use App\Support\ManagerPositions;

/**
 * The write behind the registries grid's inline cell edit (spec 0206, D-2):
 * the cell's one field goes through UpdateRegistryRequest and then
 * RegistryService::update() — the same pair PATCH /api/registries/{registry}
 * runs, so the supplier/qualified-supplier normalisation and the assignment
 * notifications fire exactly as from the form. The card is never touched
 * (`toProfile()` is null without `personal_data`).
 */
final class RegistryCellWriter
{
    private const string ROUTE_PARAMETER = 'registry';

    private const string MANAGER_SLOTS_FIELD = 'manager_slots';

    public function __construct(
        private readonly FormRequestCellValidator $formRequest,
        private readonly RegistryService $service,
    ) {}

    public function write(Registry $registry, string $fieldKey, mixed $value, User $actor): Registry
    {
        // Step 1: the cell edits the team as a list of people (D-3).
        $payload = [$fieldKey => $fieldKey === self::MANAGER_SLOTS_FIELD ? ManagerPositions::slotsFor($registry, $value) : $value];

        // Step 2: the form's own rules, then its own service.
        $request = $this->formRequest->validate(UpdateRegistryRequest::class, self::ROUTE_PARAMETER, $registry, $actor, $payload);

        return $this->service->update($actor, $registry, $request->toData(), $request->toProfile());
    }
}
