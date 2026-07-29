<?php

namespace App\Http\Controllers\Abstract;

use App\CustomFields\CustomFieldEntityRegistry;
use App\Enums\HttpStatusEnum;
use App\Exceptions\ExternalApiException;
use App\Models\Concerns\HasCustomFields;
use App\Services\TeamsWebhookService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class BaseApiController
{
    const int MAX_LIMIT = 100;

    /** HELPERS */
    public function paginatedResponse(
        mixed $items,
        int $total,
        int $offset = 1,
        int $limit = self::MAX_LIMIT,
        ?string $exportLink = null,
        ?array $meta = null
    ): JsonResponse {
        $payload = [
            'items' => $items,
            'export_link' => $exportLink,
            'pagination' => [
                'total' => $total,
                'offset' => $offset,
                'limit' => $limit,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ];

        if (! is_null($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload);
    }

    public function response(mixed $data): JsonResponse
    {
        return response()->json($data);
    }

    public function validateRequest(
        Request $request
    ): void {
        $maxLimit = self::MAX_LIMIT;
        $request->validate([
            'offset' => 'sometimes|integer|min:0',
            'limit' => "sometimes|integer|min:1|max:$maxLimit",
        ]);
    }

    public function limit(mixed $page = 1): int
    {
        return (int) ($page ?? 1);
    }

    public function offset(mixed $perPage = 15): int
    {
        return (int) ($perPage ?? 15);
    }

    protected function ok(mixed $data = null, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->withCustomFields($data),
        ], $status->value);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->ok($data, $message, HttpStatusEnum::CREATED);
    }

    /**
     * Same envelope as ok(), plus a top-level `permissions` block (spec 0004 —
     * centralized authorization metadata): `{ success, message, data, permissions }`.
     *
     * @param  array<string, mixed>  $permissions
     */
    protected function okWithPermissions(
        mixed $data,
        array $permissions,
        string $message = 'OK',
        HttpStatusEnum $status = HttpStatusEnum::OK,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->withCustomFields($data),
            'permissions' => $permissions,
        ], $status->value);
    }

    /**
     * When the payload is a single Resource wrapping a custom-fieldable model,
     * merge its custom field values as a sibling of the native fields
     * (spec 0021: `data = { ...native, custom_fields: {...} }`). No-op otherwise,
     * so every module gains custom fields in its detail output without touching
     * its Resource.
     */
    protected function withCustomFields(mixed $data): mixed
    {
        if (! $data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data;
        }

        $model = $data->resource;

        if (! $model instanceof Model
            || ! in_array(HasCustomFields::class, class_uses_recursive($model), true)
            || app(CustomFieldEntityRegistry::class)->entityTypeForModel($model) === null) {
            return $data;
        }

        $resolved = $data->resolve(request());
        $resolved['custom_fields'] = (object) $model->custom_fields;

        return $resolved;
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, HttpStatusEnum::NO_CONTENT->value);
    }

    protected function fail(string $message, int $status, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (! is_null($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Convert unexpected controller exceptions into a consistent API response.
     */
    protected function handleControllerException(Throwable $exception, string $method, array $parameters = []): JsonResponse
    {
        // A validation error is a client (422) fault, not a backend incident:
        // surface its field errors and skip the error log / Teams alert. This
        // covers ValidationException thrown deep in a service or model event
        // (e.g. the custom-field write pipeline, spec 0021) that would otherwise
        // be caught here and degraded to a 500.
        if ($exception instanceof ValidationException) {
            return $this->fail(
                $exception->getMessage(),
                HttpStatusEnum::UNPROCESSABLE_ENTITY->value,
                $exception->errors(),
            );
        }

        $status = $this->resolveExceptionStatus($exception);
        $message = $this->resolveExceptionMessage($exception, $status);

        // A deliberate `abort(4xx, ...)` is a business rule refusing the
        // request (e.g. an import step guarded by the run's status), not a
        // backend incident: answer with the envelope and skip the error log /
        // Teams alert, which would otherwise page on an expected client fault.
        // Only explicit HTTP aborts qualify: AuthorizationException and
        // ModelNotFoundException are not HttpExceptionInterface and keep being
        // logged, and a 5xx abort (e.g. ExternalApiException) still is.
        if ($exception instanceof HttpExceptionInterface
            && $status < HttpStatusEnum::INTERNAL_SERVER_ERROR->value) {
            return $this->fail($message, $status);
        }

        $backendTimestamp = now()->toIso8601String();

        Log::error('[BACKEND] API internal error', [
            'log_origin' => 'backend',
            'backend_timestamp' => $backendTimestamp,
            'controller' => static::class,
            'action' => $method,
            'status' => $status,
            'message' => $exception->getMessage(),
            'exception' => get_class($exception),
            'parameters' => $parameters,
            'url' => request()->fullUrl(),
            'method_http' => request()->method(),
            'user_id' => auth()->id(),

            'error code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack trace' => $exception->getTraceAsString(),
        ]);

        if (config('services.teams.enabled')) {
            app(TeamsWebhookService::class)->sendError(
                title: '[BACKEND] API internal error',
                message: $exception->getMessage(),
                facts: [
                    'Log Origin' => 'backend',
                    'Backend Timestamp' => $backendTimestamp,
                    'Controller' => static::class,
                    'Action' => $method,
                    'Status' => $status,
                    'Exception' => get_class($exception),
                    'URL' => request()->fullUrl(),
                    'HTTP Method' => request()->method(),
                    'User ID' => auth()->id() ?? 'guest',

                    'error code' => $exception->getCode(),
                    'get message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'stack trace' => $exception->getTraceAsString(),
                ]
            );
        }

        return $this->fail($message, $status);
    }

    /**
     * Resolve the HTTP status associated with the thrown exception.
     */
    protected function resolveExceptionStatus(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof AuthorizationException => HttpStatusEnum::FORBIDDEN->value,
            $exception instanceof ModelNotFoundException => HttpStatusEnum::NOT_FOUND->value,
            // External migration source failure (spec 0013): 502 (connection/
            // non-2xx) or 504 (timeout) — status carried on the exception
            // itself, never inferred from a generic HttpExceptionInterface.
            $exception instanceof ExternalApiException => $exception->status(),
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => HttpStatusEnum::INTERNAL_SERVER_ERROR->value,
        };
    }

    /**
     * Return a safe error message for the API response.
     */
    protected function resolveExceptionMessage(Throwable $exception, int $status): string
    {
        if ($status >= HttpStatusEnum::INTERNAL_SERVER_ERROR->value && ! app()->hasDebugModeEnabled()) {
            return 'An unexpected error occurred.';
        }

        return $exception->getMessage() ?: 'An unexpected error occurred.';
    }
}
