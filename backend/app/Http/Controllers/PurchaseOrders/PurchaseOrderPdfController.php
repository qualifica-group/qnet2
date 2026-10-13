<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseOrders;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\Exceptions\InvalidDocumentLayoutConfigException;
use App\Services\PurchaseOrders\PurchaseOrderLayoutUnavailableException;
use App\Services\PurchaseOrders\PurchaseOrderPdfRenderer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * GET /api/purchase-orders/{purchaseOrder}/pdf (spec 0210): the order as a
 * `.pdf`, a pure READ gated by `purchase-orders.view`, generated in-request
 * and streamed back, nothing persisted.
 */
class PurchaseOrderPdfController extends BaseApiController
{
    use AuthorizesRequests;

    private const string CONTENT_TYPE = 'application/pdf';

    public function __construct(private readonly PurchaseOrderPdfRenderer $renderer) {}

    public function __invoke(Request $request, PurchaseOrder $purchaseOrder): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('view', $purchaseOrder);

            /** @var User $actor */
            $actor = $request->user();
            $pdf = $this->renderer->render($purchaseOrder, $actor);

            return response()->streamDownload(
                static function () use ($pdf): void {
                    echo $pdf['bytes'];
                },
                $pdf['filename'],
                ['Content-Type' => self::CONTENT_TYPE],
            );
        } catch (PurchaseOrderLayoutUnavailableException $exception) {
            return $this->fail($exception->getMessage(), HttpStatusEnum::UNPROCESSABLE_ENTITY->value);
        } catch (InvalidDocumentLayoutConfigException $exception) {
            return $this->fail($exception->getMessage(), HttpStatusEnum::UNPROCESSABLE_ENTITY->value, $exception->errors());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchase_order' => $purchaseOrder->id]);
        }
    }
}
