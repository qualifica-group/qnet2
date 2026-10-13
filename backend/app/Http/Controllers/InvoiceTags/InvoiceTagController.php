<?php

declare(strict_types=1);

namespace App\Http\Controllers\InvoiceTags;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\InvoiceTags\StoreInvoiceTagRequest;
use App\Http\Requests\InvoiceTags\UpdateInvoiceTagRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\InvoiceTagResource;
use App\Models\InvoiceTag;
use App\Models\User;
use App\Services\InvoiceTagService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `invoice-tags` resource (spec 0247): thin controller,
 * validation in the FormRequests, authorization in InvoiceTagPolicy, logic in
 * InvoiceTagService. show/store/update attach the `permissions` block.
 *
 * @see InvoiceTagService
 */
class InvoiceTagController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InvoiceTagService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/invoice-tags/{invoiceTag}
     */
    public function show(Request $request, InvoiceTag $invoiceTag): JsonResponse
    {
        try {
            $this->authorize('view', $invoiceTag);

            return $this->okWithPermissions(
                new InvoiceTagResource($invoiceTag),
                $this->buildPermissions($request->user(), $invoiceTag),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['invoiceTag' => $invoiceTag->id]);
        }
    }

    /**
     * POST /api/invoice-tags
     */
    public function store(StoreInvoiceTagRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', InvoiceTag::class);

            $invoiceTag = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new InvoiceTagResource($invoiceTag),
                $this->buildPermissions($request->user(), $invoiceTag),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/invoice-tags/{invoiceTag}
     */
    public function update(UpdateInvoiceTagRequest $request, InvoiceTag $invoiceTag): JsonResponse
    {
        try {
            $this->authorize('update', $invoiceTag);

            $invoiceTag = $this->service->update($invoiceTag, $request->toData());

            return $this->okWithPermissions(
                new InvoiceTagResource($invoiceTag),
                $this->buildPermissions($request->user(), $invoiceTag),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['invoiceTag' => $invoiceTag->id]);
        }
    }

    /**
     * DELETE /api/invoice-tags/{invoiceTag} (409 when an invoice carries it).
     */
    public function destroy(InvoiceTag $invoiceTag): JsonResponse
    {
        try {
            $this->authorize('delete', $invoiceTag);

            $this->service->delete($invoiceTag);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['invoiceTag' => $invoiceTag->id]);
        }
    }

    /**
     * POST /api/invoice-tags/reorder: gated on `invoice-tags.update` directly,
     * since a bulk reorder has no single model for the Policy.
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('invoice-tags.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (InvoiceTag $invoiceTag): array => [
                'id' => $invoiceTag->id,
                'sort_order' => $invoiceTag->sort_order,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?InvoiceTag $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('invoice-tags'), $actor, $model);
    }
}
