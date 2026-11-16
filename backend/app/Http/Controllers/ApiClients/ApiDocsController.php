<?php

namespace App\Http\Controllers\ApiClients;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\ApiClient;
use App\Services\ApiDocs\OpenApiDocumentProvider;
use App\Services\ApiDocs\PostmanCollectionBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Integrator-facing documentation of the /api endpoints (specs 0209, 0210). Both
 * payloads are delivered as files, so they are NOT wrapped in the envelope; the
 * status, refresh and "not generated yet" answers are. Reading never generates:
 * only `refresh` (or the `api-docs:warm` command) does.
 */
class ApiDocsController extends BaseApiController
{
    use AuthorizesRequests;

    private const string POSTMAN_FILENAME = 'qnet-api.postman_collection.json';

    public function __construct(
        private readonly OpenApiDocumentProvider $documents,
        private readonly PostmanCollectionBuilder $postman,
    ) {}

    public function openapi(): JsonResponse
    {
        try {
            $this->authorize('viewAny', ApiClient::class);

            $document = $this->documents->cached();

            return $document === null ? $this->notGenerated() : response()->json($document);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function postman(): JsonResponse
    {
        try {
            $this->authorize('viewAny', ApiClient::class);

            $document = $this->documents->cached();

            if ($document === null) {
                return $this->notGenerated();
            }

            return response()
                ->json($this->postman->build($document))
                ->header('Content-Disposition', 'attachment; filename="'.self::POSTMAN_FILENAME.'"');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * Whether a document exists, when it was generated and whether a regeneration runs.
     */
    public function status(): JsonResponse
    {
        try {
            $this->authorize('viewAny', ApiClient::class);

            return $this->ok($this->documents->status());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * 202: the regeneration runs in the background; the client polls `status`.
     */
    public function refresh(): JsonResponse
    {
        try {
            $this->authorize('refreshDocs', ApiClient::class);

            $this->documents->refresh();

            return $this->ok(
                $this->documents->status(),
                __('The documentation is being regenerated.'),
                HttpStatusEnum::ACCEPTED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function notGenerated(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('The documentation has not been generated yet.'),
            'data' => $this->documents->status(),
        ], HttpStatusEnum::NOT_FOUND->value);
    }
}
