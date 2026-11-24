<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ArchivioSmartSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the inbound ArchivioSmart webhook (spec 0428, D-6): the `Signature`
 * header is the hex HMAC-SHA256 of the raw body with the saved `whsec_...` secret.
 * Without a configured secret the endpoint does not exist (404). Every call is
 * logged by outcome only: never the secret, the signature or the body.
 */
final class VerifyArchivioSmartSignature
{
    private const string SIGNATURE_HEADER = 'Signature';

    private const string HASH_ALGORITHM = 'sha256';

    public function handle(Request $request, Closure $next): Response
    {
        // Step 1: a secret that is not configured means the endpoint does not exist
        $secret = ArchivioSmartSetting::current()?->webhook_secret;
        if (! is_string($secret) || $secret === '') {
            Log::warning('ArchivioSmart webhook refused.', ['reason' => 'secret_not_configured']);

            return response()->json(['success' => false, 'message' => __('archivio_smart.webhook_not_found')], 404);
        }

        // Step 2: constant-time comparison on the raw body
        $provided = $request->header(self::SIGNATURE_HEADER);
        $expected = hash_hmac(self::HASH_ALGORITHM, $request->getContent(), $secret);

        if (! is_string($provided) || ! hash_equals($expected, strtolower($provided))) {
            Log::warning('ArchivioSmart webhook refused.', ['reason' => 'invalid_signature']);

            return response()->json(['success' => false, 'message' => __('archivio_smart.webhook_invalid_signature')], 401);
        }

        return $next($request);
    }
}
