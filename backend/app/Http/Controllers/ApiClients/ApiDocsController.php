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
 * payloads are delivered as files, so they are NOT wrapped in the envelope;
 * only the 202 "still generating" answer is.
 */
class ApiDocsController extends BaseApiController
{
    use AuthorizesRequests;

    private const int RETRY_AFTER_SECONDS = 5;

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

            return $document === null ? $this->preparing() : response()->json($document);
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
                return $this->preparing();
            }

            return response()
                ->json($this->postman->build($document))
                ->header('Content-Disposition', 'attachment; filename="'.self::POSTMAN_FILENAME.'"');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * 202 while the document is generated in the background; the client retries.
     */
    private function preparing(): JsonResponse
    {
        $this->documents->ensureGenerating();

        return response()->json([
            'success' => true,
            'message' => __('The documentation is being prepared, try again in a few seconds.'),
            'data' => ['status' => 'generating'],
        ], 202)->header('Retry-After', (string) self::RETRY_AFTER_SECONDS);
    }
}
