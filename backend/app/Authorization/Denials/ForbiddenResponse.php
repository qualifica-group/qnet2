<?php

namespace App\Authorization\Denials;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The one builder of every 403 body of /api/* (spec 0327, D-8):
 * `{ success, message, authorization: { reason, permission } }`.
 */
class ForbiddenResponse
{
    public const string REASON_MISSING_PERMISSION = 'missing_permission';

    public const string REASON_DENIED = 'denied';

    /** The framework default text: it carries no information, so it is not an explicit reason. */
    private const string FRAMEWORK_DEFAULT_MESSAGE = 'This action is unauthorized.';

    public function __construct(private readonly AuthorizationDenialLog $log) {}

    public function make(Throwable $exception): JsonResponse
    {
        // Step 1: an explicit reason wins over anything recorded earlier
        $explicit = $this->explicitMessage($exception);

        if ($explicit !== null) {
            return $this->build(self::REASON_DENIED, null, __($explicit));
        }

        // Step 2: a denied permission names itself
        $permission = $this->log->deniedPermission();

        if ($permission !== null) {
            return $this->build(
                self::REASON_MISSING_PERMISSION,
                $permission,
                __('You do not have the «:permission» permission.', ['permission' => $permission]),
            );
        }

        // Step 3: nothing useful is known
        return $this->build(self::REASON_DENIED, null, __('You are not allowed to perform this action.'));
    }

    private function explicitMessage(Throwable $exception): ?string
    {
        $message = trim($exception->getMessage());

        return $message === '' || $message === self::FRAMEWORK_DEFAULT_MESSAGE ? null : $message;
    }

    private function build(string $reason, ?string $permission, string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'authorization' => [
                'reason' => $reason,
                'permission' => $permission,
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
