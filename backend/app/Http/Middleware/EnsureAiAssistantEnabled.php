<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the AI assistant API when neither surface (floating chat, page) is
 * switched on in config/ai-assistant.php (spec 0347, D-16): the feature does
 * not exist for the installation, so 404 (project envelope) rather than 403.
 */
class EnsureAiAssistantEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ai-assistant.floating_enabled') && ! config('ai-assistant.page_enabled')) {
            return response()->json([
                'success' => false,
                'message' => __('Resource not found.'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
