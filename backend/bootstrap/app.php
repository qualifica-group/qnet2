<?php

use App\Http\Middleware\CaptureCustomFields;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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

        // User directive 2026-08-03: the API answers in the language the
        // client is using. PREPENDED so the locale is already set when
        // anything downstream renders a message — a FormRequest rejecting the
        // payload throws from the route middleware stack, before any
        // controller runs.
        $middleware->api(prepend: [SetLocale::class]);
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
    })->create();
