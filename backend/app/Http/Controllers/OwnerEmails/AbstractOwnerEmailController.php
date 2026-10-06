<?php

declare(strict_types=1);

namespace App\Http\Controllers\OwnerEmails;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use App\Services\OutboundEmails\EmailOwner;
use App\Services\OutboundEmails\EmailOwnerRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Shared plumbing of every owner-scoped email controller (spec 0195, D-10):
 * owner-level authorization through the abilities the owner's EmailOwner
 * declares, the acting user, and the error envelope. A concrete controller
 * per owner only re-declares each action with the owner's TYPED route
 * parameter (so implicit route binding runs) and delegates to the base.
 */
abstract class AbstractOwnerEmailController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(protected readonly EmailOwnerRegistry $owners) {}

    protected function emailOwner(Model $owner): EmailOwner
    {
        return $this->owners->for($owner);
    }

    protected function authorizeView(Model $owner): void
    {
        $this->authorize($this->emailOwner($owner)->viewAbility(), $owner);
    }

    protected function authorizeSend(Model $owner): void
    {
        $this->authorize($this->emailOwner($owner)->sendAbility(), $owner);
    }

    protected function actor(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function failed(Throwable $exception, string $method, Model $owner, array $extra = []): JsonResponse
    {
        return $this->handleControllerException($exception, $method, [$owner->getMorphClass() => $owner->getKey(), ...$extra]);
    }
}
