<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Identity\CheckIdentityDuplicatesRequest;
use App\Http\Resources\IdentityDuplicateMatchResource;
use App\Models\Referent;
use App\Models\Registry;
use App\Services\IdentityDuplicateFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * POST /api/identity/duplicate-check — live, non-blocking duplicate check
 * shared by the anagrafica and referente create forms (spec 0037, extended by
 * the user directive 2026-09-09): given a tax_code, a vat_number and/or
 * email/phone/mobile contacts, returns the (max 5) EXISTING holders inside the
 * identity namespace, with the matched channel(s).
 *
 * Thin invokable controller: validation (CheckIdentityDuplicatesRequest),
 * server-side authorization, Service call, Resource response.
 *
 * Authorization mirrors what the check FEEDS: the two create forms it is
 * rendered on. An actor allowed to create either an anagrafica or a referente
 * may run it — a single gate would lock out the other form's operators.
 *
 * @see IdentityDuplicateFinder::find
 */
class IdentityDuplicateCheckController extends BaseApiController
{
    public function __construct(private readonly IdentityDuplicateFinder $finder) {}

    public function __invoke(CheckIdentityDuplicatesRequest $request): JsonResponse
    {
        try {
            abort_unless(
                Gate::allows('create', Registry::class) || Gate::allows('create', Referent::class),
                403,
            );

            $matches = $this->finder->find($request->toCriteria());

            return $this->ok(['matches' => IdentityDuplicateMatchResource::collection($matches)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
