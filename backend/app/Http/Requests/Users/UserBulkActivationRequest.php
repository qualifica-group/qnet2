<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload of POST /api/users/bulk-activation (spec 0310). The per-user
 * authorization (policy + `is_active` field permission) lives in
 * UserBulkActivation; this only requires the base update ability.
 */
class UserBulkActivationRequest extends FormRequest
{
    public const int MAX_IDS = 200;

    public function authorize(): bool
    {
        /** @var User $actor */
        $actor = $this->user();

        return $actor->can('users.update');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map('intval', $this->validated('ids'));
    }
}
