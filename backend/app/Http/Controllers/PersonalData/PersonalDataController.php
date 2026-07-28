<?php

namespace App\Http\Controllers\PersonalData;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PersonalData\IndexPersonalDataRequest;
use App\Http\Requests\PersonalData\StorePersonalDataRequest;
use App\Http\Requests\PersonalData\UpdatePersonalDataRequest;
use App\Http\Resources\PersonalDataResource;
use App\Models\PersonalData;
use App\Services\PersonalDataService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the PersonalData resource (the identity card an owner
 * holds via morphOne).
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (PersonalDataPolicy), Service call, response. No business logic, no queries.
 * Authorization is re-enforced server-side on every action regardless of what
 * the frontend shows or hides.
 *
 * The card holds personal data (fiscal identifiers, birth date) re-exposed by
 * PersonalDataResource — its release is gated on Legal sign-off (see ADR 0006).
 *
 * @see PersonalDataService
 */
class PersonalDataController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly PersonalDataService $service) {}

    /**
     * GET /api/personal-data?personable_type={alias}&personable_id={id} — the
     * single card a polymorphic owner holds (morphOne), with its contacts and
     * addresses, or null when the owner has no card yet. Lets the frontend load
     * a card by owner without knowing its id.
     */
    public function index(IndexPersonalDataRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', PersonalData::class);

            $card = $request->owner()->personalData()->with(['contacts', 'addresses', 'birthCity', 'residenceCity'])->first();

            return $this->ok($card ? new PersonalDataResource($card) : null);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/personal-data/{personalData} — a single card with its contacts
     * and addresses.
     */
    public function show(PersonalData $personalData): JsonResponse
    {
        try {
            $this->authorize('view', $personalData);

            $personalData->load(['contacts', 'addresses', 'birthCity', 'residenceCity']);

            return $this->ok(new PersonalDataResource($personalData));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['personal_data' => $personalData->id]);
        }
    }

    /**
     * POST /api/personal-data — create a card for a polymorphic owner.
     */
    public function store(StorePersonalDataRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', PersonalData::class);

            $card = $this->service->createFor($request->owner(), $request->toData());

            return $this->created(new PersonalDataResource($card));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/personal-data/{personalData} — full update of the card.
     */
    public function update(UpdatePersonalDataRequest $request, PersonalData $personalData): JsonResponse
    {
        try {
            $this->authorize('update', $personalData);

            $card = $this->service->update($personalData, $request->toData());

            return $this->ok(new PersonalDataResource($card->load(['contacts', 'addresses', 'birthCity', 'residenceCity'])));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['personal_data' => $personalData->id]);
        }
    }

    /**
     * DELETE /api/personal-data/{personalData} — delete the card (cascades its
     * contacts and addresses).
     */
    public function destroy(PersonalData $personalData): JsonResponse
    {
        try {
            $this->authorize('delete', $personalData);

            $this->service->delete($personalData);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['personal_data' => $personalData->id]);
        }
    }
}
