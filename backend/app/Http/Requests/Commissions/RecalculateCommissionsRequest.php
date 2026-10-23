<?php

declare(strict_types=1);

namespace App\Http\Requests\Commissions;

use App\Enums\CommissionRecalculationMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of the two explicit recalculation endpoints (spec 0308): the mandatory
 * answer of the controlled-choice dialog. Authorization is enforced in the
 * controllers (Policy), like every other action of these modules.
 */
class RecalculateCommissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(CommissionRecalculationMode::class)],
        ];
    }

    public function mode(): CommissionRecalculationMode
    {
        return CommissionRecalculationMode::from($this->safe()->string('mode')->toString());
    }
}
