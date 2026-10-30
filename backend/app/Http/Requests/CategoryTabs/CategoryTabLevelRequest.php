<?php

declare(strict_types=1);

namespace App\Http\Requests\CategoryTabs;

use App\Services\CategoryTabs\CategoryTabLevel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The optional `level` query of a category tab strip endpoint (spec 0337):
 * absent = every category as before, `root` = the root categories only.
 * Authorization stays in the controller (`{module}.viewAny`).
 */
class CategoryTabLevelRequest extends FormRequest
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
            'level' => ['sometimes', 'in:'.CategoryTabLevel::Root->value],
        ];
    }

    public function level(): CategoryTabLevel
    {
        return CategoryTabLevel::tryFrom((string) $this->input('level')) ?? CategoryTabLevel::All;
    }
}
