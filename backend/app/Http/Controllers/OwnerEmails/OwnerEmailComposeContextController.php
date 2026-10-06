<?php

declare(strict_types=1);

namespace App\Http\Controllers\OwnerEmails;

use App\Services\OutboundEmails\EmailOwnerRegistry;
use App\Services\OutboundEmails\OutboundEmailComposeContextBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * `GET .../emails/compose-context` (spec 0175, D-5/D-6/D-7/D-9; generalized
 * by owner in spec 0195, D-10). Gated by the SEND ability (not the view one):
 * the compose context only matters to an actor about to draft/send, mirroring
 * render-template.
 */
abstract class OwnerEmailComposeContextController extends AbstractOwnerEmailController
{
    public function __construct(EmailOwnerRegistry $owners, private readonly OutboundEmailComposeContextBuilder $builder)
    {
        parent::__construct($owners);
    }

    protected function composeContext(Request $request, Model $owner): JsonResponse
    {
        try {
            $this->authorizeSend($owner);

            return $this->ok($this->builder->build($owner, $this->actor($request)));
        } catch (Throwable $exception) {
            return $this->failed($exception, '__invoke', $owner);
        }
    }
}
