<?php

namespace App\Http\Middleware;

use App\Services\WorkOrders\Chat\WorkOrderChatAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the project work order chat API when CHAT_ENABLED is off
 * (config/chat.php, spec 0432): the feature does not exist for the
 * installation, so 404 (project envelope) rather than 403, like the AI
 * assistant's switch (EnsureAiAssistantEnabled).
 */
class EnsureWorkOrderChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! WorkOrderChatAccess::isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => __('Resource not found.'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
