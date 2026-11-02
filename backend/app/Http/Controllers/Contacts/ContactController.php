<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PersonalData\StoreContactRequest;
use App\Http\Requests\PersonalData\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Services\ContactService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the Contact resource (a reachable channel owned by any
 * entity via morphMany).
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (ContactPolicy), Service call, response. The "at most one primary per
 * owner+type" invariant is enforced by ContactService, not here.
 *
 * @see ContactService
 */
class ContactController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ContactService $service) {}

    /**
     * GET /api/contacts/{contact} — a single contact channel.
     */
    public function show(Contact $contact): JsonResponse
    {
        try {
            $this->authorize('view', $contact);

            return $this->ok(new ContactResource($contact));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contact' => $contact->id]);
        }
    }

    /**
     * POST /api/contacts — create a contact for a polymorphic owner.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        try {
            $owner = $request->owner();
            $this->authorize('create', [Contact::class, $owner]);

            $contact = $this->service->createFor($owner, $request->toData());

            return $this->created(new ContactResource($contact));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/contacts/{contact} — full update of the contact.
     */
    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        try {
            $this->authorize('update', $contact);

            $contact = $this->service->update($contact, $request->toData());

            return $this->ok(new ContactResource($contact));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contact' => $contact->id]);
        }
    }

    /**
     * DELETE /api/contacts/{contact} — delete the contact.
     */
    public function destroy(Contact $contact): JsonResponse
    {
        try {
            $this->authorize('delete', $contact);

            $this->service->delete($contact);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contact' => $contact->id]);
        }
    }
}
