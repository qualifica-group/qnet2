<?php

namespace App\Http\Controllers\ApiClients;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ApiClients\StoreApiClientRequest;
use App\Http\Requests\ApiClients\UpdateApiClientRequest;
use App\Http\Resources\ApiClientResource;
use App\Models\ApiClient;
use App\Services\ApiClients\ApiClientService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Admin endpoints for the external API integration clients (spec 0209).
 *
 * Thin controller: validation (FormRequest), authorization (ApiClientPolicy),
 * Service call, envelope. The list goes through the table framework
 * (`api-clients` domain), not through this controller.
 *
 * @see ApiClientService
 */
class ApiClientController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ApiClientService $service) {}

    public function show(ApiClient $apiClient): JsonResponse
    {
        try {
            $this->authorize('view', $apiClient);

            return $this->ok(new ApiClientResource($this->service->loadDetail($apiClient)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['api_client' => $apiClient->id]);
        }
    }

    public function store(StoreApiClientRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ApiClient::class);

            $result = $this->service->create($request->safe()->all(), $request->user());

            return $this->ok($this->withKey($result), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateApiClientRequest $request, ApiClient $apiClient): JsonResponse
    {
        try {
            $this->authorize('update', $apiClient);

            $client = $this->service->update($apiClient, $request->safe()->all());

            return $this->ok(new ApiClientResource($client));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['api_client' => $apiClient->id]);
        }
    }

    /**
     * POST /api/api-clients/{apiClient}/rotate-key — revokes every previous key (not the client-login user tokens).
     */
    public function rotateKey(Request $request, ApiClient $apiClient): JsonResponse
    {
        try {
            $this->authorize('update', $apiClient);

            return $this->ok($this->withKey($this->service->rotateKey($apiClient, $request->user())), 'Key rotated');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['api_client' => $apiClient->id]);
        }
    }

    public function destroy(ApiClient $apiClient): JsonResponse
    {
        try {
            $this->authorize('delete', $apiClient);

            $this->service->delete($apiClient);

            return $this->ok(null, 'Deleted');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['api_client' => $apiClient->id]);
        }
    }

    /**
     * @param  array{client: ApiClient, plain_text_key: string}  $result
     * @return array{client: ApiClientResource, plain_text_key: string}
     */
    private function withKey(array $result): array
    {
        return ['client' => new ApiClientResource($result['client']), 'plain_text_key' => $result['plain_text_key']];
    }
}
