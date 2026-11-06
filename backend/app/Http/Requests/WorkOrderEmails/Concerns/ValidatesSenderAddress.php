<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderEmails\Concerns;

use App\Models\User;
use App\Services\OutboundEmails\OutboundEmailSenderAddresses;
use Illuminate\Validation\Rule;

/**
 * Optional `from_address` of a draft (spec 0361, D-1/D-2): `null` means the
 * actor's login email; any other value must be one of the actor's own sender
 * addresses, never a free address.
 */
trait ValidatesSenderAddress
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function senderAddressRules(): array
    {
        /** @var User $actor */
        $actor = $this->user();

        return [
            'from_address' => ['sometimes', 'nullable', 'string', Rule::in(app(OutboundEmailSenderAddresses::class)->for($actor))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['from_address.in' => __('outbound_emails.sender_not_allowed')];
    }
}
