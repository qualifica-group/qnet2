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
     * `$reservedPositions`: positions the cell does not show (spec 0382
     * D-11, the named positions of a category tab), left as they are.
     *
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int>  $reservedPositions
     * @return array<string, array<int, int|null>>
     */
    public function payload(Quote $quote, User $actor, RequestModule $module, array $userIds, array $reservedPositions = []): array
    {
        $request = $this->formRequest->validate(
            UpdateRequestRequest::class,
            self::ROUTE_PARAMETER,
            $quote,
            $actor,
            [self::MANAGER_SLOTS_FIELD => ManagerPositions::slotsFor($quote, $userIds, $reservedPositions)],
            [RequestModule::ROUTE_DEFAULT => $module->value],
        );

        return $request->safe()->only([self::MANAGER_SLOTS_FIELD]);
    }
}
