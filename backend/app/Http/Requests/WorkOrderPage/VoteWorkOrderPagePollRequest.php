<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderPage;

use App\Http\Requests\WorkOrderChat\WorkOrderChatRequest;
use App\Models\WorkOrderPagePoll;
use Illuminate\Validation\Rule;

/**
 * PUT of a vote (spec 0420, D-7): the key must be one of the poll's options.
 */
class VoteWorkOrderPagePollRequest extends WorkOrderChatRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var WorkOrderPagePoll $poll */
        $poll = $this->route('pagePoll');

        return ['option_key' => ['required', 'string', Rule::in($poll->optionKeys())]];
    }
}
