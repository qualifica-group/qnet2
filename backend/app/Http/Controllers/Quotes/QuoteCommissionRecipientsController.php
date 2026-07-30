<?php

declare(strict_types=1);

namespace App\Http\Controllers\Quotes;

use App\DataObjects\Commissions\CommissionRecipient;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Quotes\QuoteCommissionRecipientsRequest;
use App\Models\Product;
use App\Models\Quote;
use App\Services\Commissions\CommissionRecipientResolver;
use App\Services\Commissions\QuoteCommissionPayloadRedactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Exposes, per commission role, the ONE recipient a quote line may award that
 * role to — or null when nothing was selected upstream, which the client
 * renders as "this role is not available". The identities are resolved by the
 * same CommissionRecipientResolver the write-side guard uses, so the locked
 * dialog and the 422 can never disagree.
 */
class QuoteCommissionRecipientsController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CommissionRecipientResolver $resolver,
        private readonly QuoteCommissionPayloadRedactor $redactor,
    ) {}

    public function __invoke(QuoteCommissionRecipientsRequest $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $validated */
            $validated = $request->validated();

            // Step 1: same authorization ceiling as the defaults endpoint.
            $quote = null;
            if (isset($validated['quote_id'])) {
                $quote = Quote::findOrFail((int) $validated['quote_id']);
                $this->authorize('update', $quote);
            } else {
                $this->authorize('create', Quote::class);
            }

            $permissions = $this->redactor->permissions($request->user(), $quote);

            abort_unless(
                $permissions['commissions']->visible && $permissions['commission_recipient']->visible,
                403,
            );

            // Step 2: resolve the admissible recipient of every role.
            $product = Product::findOrFail((int) $validated['product_id']);
            $recipients = $this->resolver->resolve(
                $product,
                $this->roleId($validated, 'commercial_id') ?? $quote?->commercial_id,
                $this->roleId($validated, 'reporter_id') ?? $quote?->reporter_id,
                $this->roleId($validated, 'supervisor_id') ?? $quote?->supervisor_id,
            );

            // Step 3: hydrate each one with its display name.
            return $this->ok(array_map(
                fn (?CommissionRecipient $recipient): ?array => $this->hydrate($recipient),
                $recipients,
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /** @param array<string, mixed> $validated */
    private function roleId(array $validated, string $key): ?int
    {
        return isset($validated[$key]) ? (int) $validated[$key] : null;
    }

    /** @return array<string, mixed>|null */
    private function hydrate(?CommissionRecipient $recipient): ?array
    {
        if ($recipient === null) {
            return null;
        }

        /** @var class-string<Model>|null $class */
        $class = Relation::getMorphedModel($recipient->type);
        $model = $class === null ? null : $class::query()->find($recipient->id);

        if ($model === null) {
            return null;
        }

        return [
            'type' => $recipient->type,
            'id' => $recipient->id,
            'name' => $model->name,
        ];
    }
}
