<?php

namespace App\Services\ApiDocs\Infer;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\EnumCaseType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\NeverType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\JsonResponse;

/**
 * Teaches Scramble the response envelope of the BaseApiController helpers
 * (`ok`, `created`, `okWithPermissions`): `{ success, message, data[, permissions] }`
 * with the Resource passed to the helper as `data`.
 */
class ApiEnvelopeReturnTypeExtension implements MethodReturnTypeExtension
{
    /** Helper name => position of its optional `$status` argument (null: fixed status). */
    private const array STATUS_ARGUMENT_POSITION = [
        'ok' => 2,
        'created' => null,
        'okWithPermissions' => 3,
    ];

    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(BaseApiController::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        // The error helper only renders failures, already documented by Scramble's
        // exception extensions: dropping it keeps a bare string branch out of the response.
        if ($event->name === 'handleControllerException') {
            return new NeverType;
        }

        if (! array_key_exists($event->name, self::STATUS_ARGUMENT_POSITION)) {
            return null;
        }

        $items = [
            new ArrayItemType_('success', new BooleanType),
            new ArrayItemType_('message', new StringType),
            new ArrayItemType_('data', $event->getArg('data', 0)),
        ];

        if ($event->name === 'okWithPermissions') {
            $items[] = new ArrayItemType_('permissions', $event->getArg('permissions', 1));
        }

        return new Generic(JsonResponse::class, [new KeyedArrayType($items), new LiteralIntegerType($this->status($event)), new KeyedArrayType]);
    }

    private function status(MethodCallEvent $event): int
    {
        $position = self::STATUS_ARGUMENT_POSITION[$event->name];

        if ($position === null) {
            return HttpStatusEnum::CREATED->value;
        }

        $argument = $event->getArg('status', $position);

        return $argument instanceof EnumCaseType && $argument->name === HttpStatusEnum::class
            ? constant($argument->name.'::'.$argument->caseName)->value
            : HttpStatusEnum::OK->value;
    }
}
