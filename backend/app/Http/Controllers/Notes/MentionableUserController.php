<?php

namespace App\Http\Controllers\Notes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Notes\MentionableUsersRequest;
use App\Http\Resources\NoteMentionableUserResource;
use App\Notes\Mentions\MentionableUserDirectory;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/notes/mentionable-users — the contextual @mention lookup (spec
 * 0052 data_contract): NOT `users/for-select` (D-10, scoped to who can read
 * the record), same for-select response shape (ADR 0011) so the frontend
 * reuses useForSelect unchanged.
 *
 * Read authorization on the host record runs inside MentionableUserDirectory (D-6).
 *
 * @see MentionableUserDirectory::list
 */
class MentionableUserController extends BaseApiController
{
    public function __construct(private readonly MentionableUserDirectory $directory) {}

    public function index(MentionableUsersRequest $request): JsonResponse
    {
        try {
            $result = $this->directory->list(
                $request->user(),
                $request->entityType(),
                $request->entityId(),
                $request->mentionScope(),
                $request->toQuery(),
            );

            return $this->paginatedResponse(
                NoteMentionableUserResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
                hasMore: $result->hasMore,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
