<?php

namespace App\Http\Resources;

use App\Models\WorkOrderPagePoll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Poll shape (spec 0380 data_contract): options with their nominative voters,
 * the reader's own vote and the total. Expects `votes.user` eager-loaded.
 *
 * @mixin WorkOrderPagePoll
 */
class WorkOrderPagePollResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $votes = $this->votes->sortBy('id');

        return [
            'id' => $this->id,
            'question' => $this->question,
            'options' => array_map(function (array $option) use ($votes): array {
                $forOption = $votes->where('option_key', $option['key']);

                return [
                    'key' => $option['key'],
                    'label' => $option['label'],
                    'votes' => $forOption->count(),
                    'voters' => $forOption->map(fn ($vote): array => ['id' => $vote->user_id, 'name' => $vote->user?->name])->values()->all(),
                ];
            }, $this->options),
            'my_vote' => $votes->firstWhere('user_id', $request->user()?->id)?->option_key,
            'total_votes' => $votes->count(),
        ];
    }
}
