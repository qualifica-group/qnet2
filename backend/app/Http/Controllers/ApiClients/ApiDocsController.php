<?php

namespace App\Http\Controllers\ApiClients;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\ApiClient;
use App\Services\ApiDocs\OpenApiDocumentProvider;
use App\Services\ApiDocs\PostmanCollectionBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Integrator-facing documentation of the /api endpoints (specs 0209, 0210). Both
 * payloads are delivered as files, so they are NOT wrapped in the envelope.
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

            return response()->json($this->documents->document());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function postman(): JsonResponse
    {
        try {
            $this->authorize('viewAny', ApiClient::class);

            return response()
                ->json($this->postman->build($this->documents->document()))
                ->header('Content-Disposition', 'attachment; filename="'.self::POSTMAN_FILENAME.'"');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
