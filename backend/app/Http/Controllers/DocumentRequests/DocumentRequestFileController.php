<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\DocumentRequest;
use App\Services\DocumentRequests\DocumentDownloadException;
use App\Services\DocumentRequests\DocumentRequestFileDownloader;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Download of an uploaded file of a document request (spec 0429, D-1).
 */
class DocumentRequestFileController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentRequestFileDownloader $downloader) {}

    /**
     * GET /api/document-requests/{documentRequest}/documents/{document}/download
     */
    public function download(DocumentRequest $documentRequest, int $document): Response|JsonResponse
    {
        try {
            $this->authorize('view', $documentRequest);

            return $this->downloader->download($documentRequest, $document);
        } catch (DocumentDownloadException $exception) {
            return $this->fail($exception->getMessage(), $exception->status);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentRequest' => $documentRequest->id]);
        }
    }
}
