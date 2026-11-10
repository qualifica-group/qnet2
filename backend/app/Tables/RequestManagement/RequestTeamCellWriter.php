<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Http\Requests\RequestManagement\UpdateRequestRequest;
use App\Models\Quote;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\Table\FormRequestCellValidator;
use App\Support\ManagerPositions;

/**
 * Turns the `managers` cell's list of people into the `manager_slots` the work
 * panel would send (spec 0373 D-9), through the panel's own form request: the
 * grid follows the same team rules instead of re-declaring them.
 */
final class RequestTeamCellWriter
{
    private const string ROUTE_PARAMETER = 'quote';

    private const string MANAGER_SLOTS_FIELD = 'manager_slots';

    public function __construct(private readonly FormRequestCellValidator $formRequest) {}

    /**
     * @param  array<int, int|string>  $userIds
     * @return array<string, array<int, int|null>>
     */
    public function payload(Quote $quote, User $actor, RequestModule $module, array $userIds): array
    {
        $request = $this->formRequest->validate(
            UpdateRequestRequest::class,
            self::ROUTE_PARAMETER,
            $quote,
            $actor,
            [self::MANAGER_SLOTS_FIELD => ManagerPositions::slotsFor($quote, $userIds)],
            [RequestModule::ROUTE_DEFAULT => $module->value],
        );

        return $request->safe()->only([self::MANAGER_SLOTS_FIELD]);
    }
}
