<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A business refusal the client must tell apart from any other error with
 * the same status (spec 0404 E-D4: 409 `contracted_edit_confirmation_required`
 * asks for a confirmation, it is not a conflict to show as an error). The
 * envelope carries `code` next to `message`, both through
 * BaseApiController::handleControllerException() and when it bubbles.
 */
final class CodedHttpException extends HttpException
{
    public function __construct(int $statusCode, private readonly string $errorCode, string $message)
    {
        parent::__construct($statusCode, $message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->getStatusCode());
    }
}
