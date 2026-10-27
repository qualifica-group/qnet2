<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Workspace;
use App\Services\Workspaces\NavigationCatalogue;
use App\Support\BadgeTokens;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/workspaces (spec 0317). Access is gated by the `super-admin`
 * route middleware; rules are shared with the update request.
 */
class StoreWorkspaceRequest extends FormRequest
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
            'slug' => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique(Workspace::class, 'slug')->ignore($this->route('workspace')),
            ],
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:160'],
            'icon' => ['nullable', 'string', Rule::in(BadgeTokens::icons())],
            'color' => ['nullable', 'string', Rule::in(BadgeTokens::colors())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'navigation_keys' => ['required', 'array', 'min:1'],
            'navigation_keys.*' => ['string', 'distinct', Rule::in(app(NavigationCatalogue::class)->keys())],
        ];
    }
}
