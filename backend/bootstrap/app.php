<?php

use App\Http\Middleware\CaptureCustomFields;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\LimitStatementDuration;
use App\Http\Middleware\RecordActorWrite;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ThrottleApiClientRequests;
use App\Support\Database\StatementTimeout;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Spec 0209: routes/api.php is at its line budget, so the API
            // clients admin and client-login register from a dedicated file.
            Route::middleware(['api', 'auth:sanctum'])
                ->prefix('api')
                ->group(base_path('routes/api/api-clients.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Fail-closed hard gate for the "Migrazioni" section (spec 0013).
        $middleware->alias([
            'super-admin' => EnsureSuperAdmin::class,
        ]);

        // API-only app: there is no `login` route to redirect a guest to. The
        // framework default (`fn () => route('login')`) is evaluated by
        // Authenticate for every request that does NOT send
        // `Accept: application/json` — i.e. a plain browser navigation to a
        // protected endpoint such as /api/attachments/{id}/view — and blows up
        // with RouteNotFoundException (500) instead of the intended 401.
        // Returning null keeps the AuthenticationException redirect-less, so
        // the handler (shouldRenderJsonWhen below) renders the JSON 401.
        $middleware->redirectGuestsTo(fn () => null);

        // spec 0021 — INNESTO WRITE: captures the request-wide `custom_fields`
        // payload into the request-scoped CustomFieldRequestBag for every
        // api/* request. Pure capture (no auth logic), appended so it never
        // reorders the existing pipeline.
        $middleware->api(append: [CaptureCustomFields::class]);

        // spec 0178, D-4: remembers successful writes per actor so cached
        // aggregates are recomputed for whoever just changed the data.
        $middleware->api(append: [RecordActorWrite::class]);

        // User directive 2026-08-03: the API answers in the language the
        // client is using. PREPENDED so the locale is already set when
        // anything downstream renders a message — a FormRequest rejecting the
        // payload throws from the route middleware stack, before any
        // controller runs.
        $middleware->api(prepend: [SetLocale::class]);

        // Spec 0210, AC-008: per-client rate limit, only for requests made with
        // a token bound to an API client.
        $middleware->api(append: [ThrottleApiClientRequests::class]);

        // Per-session DB statement time limit for web/API requests only.
        $middleware->api(append: [LimitStatementDuration::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Uniform 404 envelope for unknown {domain} on api/* routes. A
        // ModelNotFoundException can be thrown from a FormRequest (e.g.
        // TableRowsRequest resolving an unregistered domain) BEFORE the
        // controller's try/catch runs, which would otherwise yield Laravel's
        // default 404 body instead of the contract's fail() shape
        // ({success:false, message}). This keeps that response on-contract
        // (status and pre-validation behaviour are unchanged).
        //
        // Note: Handler::prepareException() converts ModelNotFoundException to a
        // NotFoundHttpException (keeping the original as its previous) BEFORE
        // render callbacks run, so we match on NotFoundHttpException and only
        // handle the model-not-found case — route-not-found 404s are untouched.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*') || ! ($exception->getPrevious() instanceof ModelNotFoundException)) {
                return null;
            }

            // Generic message: never leak the internal model/definition class.
            return response()->json([
                'success' => false,
                'message' => __('Resource not found.'),
            ], Response::HTTP_NOT_FOUND);
        });

        // A query killed by the per-session statement limit (MariaDB 1969 /
        // MySQL 3024). 503 is not special-cased by the frontend axios client
        // (only 401 is) and, unlike 504, is not read by the migrations UI as
        // "external service unavailable". No SQL or class name in the body.
        $exceptions->render(function (QueryException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*') || ! StatementTimeout::isTimeout($exception)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('The operation took too long: narrow your search or filters and try again.'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
    })->create();
