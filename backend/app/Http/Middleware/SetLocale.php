<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\LocaleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the API answer in the language the client is using (user directive
 * 2026-08-03: "l'errore e' in inglese, bisogna tradurre").
 *
 * Every user-facing string the API returns — validation messages
 * (`lang/{locale}/validation.php`) and the app's own rule messages
 * (`lang/it.json`, resolved through `__()`) — is rendered in
 * `app()->getLocale()`. Without this middleware that stayed `APP_LOCALE` (en)
 * on every request except the public bootstrap one, so the Italian catalogue
 * shipped in `lang/it*` was effectively unreachable and errors surfaced in
 * English inside an Italian UI.
 *
 * The source is the `Accept-Language` header, which the frontend sets to its
 * OWN active i18next language (`api/client.ts`) rather than leaving it to the
 * browser's preferences: the language of the interface is what the operator
 * expects the messages in, and the interface already follows the user's
 * `locale` field. An unsupported/absent header falls back to
 * `config('app.locale')`, so anything that does not ask (tests, server-to-
 * server calls) keeps today's English behaviour unchanged.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(LocaleEnum::fromAcceptLanguage($request->header('Accept-Language')));

        return $next($request);
    }
}
