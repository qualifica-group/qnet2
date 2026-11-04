<?php

namespace App\Http\Controllers\Notes;

use App\DataObjects\Notes\NoteSaveResult;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Notes\IndexNoteRequest;
use App\Http\Requests\Notes\StoreNoteRequest;
use App\Http\Requests\Notes\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Services\Notes\NoteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Index/store/update/destroy for the agnostic notes component (spec 0052).
 * Thin controller: permission/ownership gate (NotePolicy — read access to
 * the host record is checked inside NoteService, D-6), FormRequest
 * validation, Service call, Resource output.
 *
 * @see NoteService
 */
class NoteController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly NoteService $service) {}

    /**
     * GET /api/notes — a custom envelope (data + meta) since `data` must stay
     * a plain Note[] (data_contract), not the {items, ...} for-select shape.
     */
    public function index(IndexNoteRequest $request): JsonResponse
    {
        try {
            $page = $this->service->listForEntity(
                $request->user(),
                $request->entityType(),
                $request->entityId(),
                $request->cursor(),
                $request->limit(),
                $request->quoteScope(),
            );

            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => $page->items
                    ->map(fn (Note $note): array => (new NoteResource($note, includeReplies: true))->resolve($request))
                    ->all(),
                'meta' => [
                    'next_cursor' => $page->nextCursor,
                    'has_more' => $page->hasMore,
                    // Spec 0085 amendment: the host record's Offerte travel
                    // with the thread, so every surface mounting the notes
                    // component (detail tab, grid row dialog, work panel)
                    // renders filter and destination without its own fetch.
                    'quotes' => $page->quoteScopes,
                    // Spec 0353: whether the composer may unlock mentions of
                    // people outside the record, and the role they would get.
                    'mention_outside' => $page->mentionOutside,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Note::class);

            $result = $this->service->create($request->user(), $request->toData());

            return $this->savedResponse($result, 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse
    {
        try {
            $this->authorize('update', $note);

            $result = $this->service->update($request->user(), $note, $request->toData());

            return $this->savedResponse($result, 'Updated', HttpStatusEnum::OK);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['note' => $note->id]);
        }
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        try {
            $this->authorize('delete', $note);

            $this->service->delete($request->user(), $note);

            return $this->ok(null, 'Deleted');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['note' => $note->id]);
        }
    }

    /**
     * Same envelope as created()/ok() plus `meta.enlisted_user_ids` (spec
     * 0353): the people this save added to the host record.
     */
    private function savedResponse(NoteSaveResult $result, string $message, HttpStatusEnum $status): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => (new NoteResource($result->note))->resolve(request()),
            'meta' => ['enlisted_user_ids' => $result->enlistedUserIds],
        ], $status->value);
    }
}
