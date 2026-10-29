<?php

namespace App\Http\Controllers\Addresses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PersonalData\StoreAddressRequest;
use App\Http\Requests\PersonalData\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the Address resource (a postal address owned by any entity
 * via morphMany).
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (AddressPolicy), Service call, response. The locating parts of an address are
 * re-exposed by AddressResource (see its privacy note).
 *
 * @see AddressService
 */
class AddressController extends BaseApiController
{
    use AuthorizesRequests;

    /**
     * Geo relations eager-loaded on every returned address so AddressResource
     * emits the city/province/state/country NAMES (whenLoaded): an
     * immediately-persisted add/edit row is then as complete as the detail tree.
     *
     * @var array<int, string>
     */
    private const array GEO_RELATIONS = ['city', 'province', 'state', 'country'];

    public function __construct(private readonly AddressService $service) {}

    /**
     * GET /api/addresses/{address} — a single address.
     */
    public function show(Address $address): JsonResponse
    {
        try {
            $this->authorize('view', $address);

            return $this->ok(new AddressResource($address->load(self::GEO_RELATIONS)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['address' => $address->id]);
        }
    }

    /**
     * POST /api/addresses — create an address for a polymorphic owner.
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        try {
            $owner = $request->owner();
            $this->authorize('create', [Address::class, $owner]);

            $address = $this->service->createFor($owner, $request->toData());

            return $this->created(new AddressResource($address->load(self::GEO_RELATIONS)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/addresses/{address} — full update of the address.
     */
    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        try {
            $this->authorize('update', $address);

            $address = $this->service->update($address, $request->toData());

            return $this->ok(new AddressResource($address->load(self::GEO_RELATIONS)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['address' => $address->id]);
        }
    }

    /**
     * DELETE /api/addresses/{address} — delete the address.
     */
    public function destroy(Address $address): JsonResponse
    {
        try {
            $this->authorize('delete', $address);

            $this->service->delete($address);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['address' => $address->id]);
        }
    }
}
