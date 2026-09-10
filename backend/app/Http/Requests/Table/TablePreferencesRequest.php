<?php

namespace App\Http\Requests\Table;

use App\DataObjects\Table\ColumnState;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the column-state payload for POST /api/tables/{domain}/preferences.
 *
 * Like TableRowsRequest, the domain is unknown at boot, so the definition is
 * resolved here from the {domain} route segment — an UNKNOWN domain surfaces as
 * 404 BEFORE validation (TableRegistry::resolve throws), never a misleading 422.
 *
 * SECURITY (ADR-0004): every `id` must be a real column of the resolved
 * definition, and only the presentation properties visible/width/order are
 * accepted — structural properties are not even validated because they can never
 * be persisted. Out-of-whitelist id or property → 422, never reaches the store.
 * `width` is clamped to a sane range so absurd values cannot be saved.
 *
 * `product_category_id` is OPTIONAL and never touches what is persisted (the
 * saved layout is per-domain, not per-tab): it only tells the controller which
 * category shape to rebuild the RESPONSE config on, so the client can refresh
 * the cache entry it actually reads (spec 0064).
 *
 * Authorization stays in the controller via the definition's viewAny.
 */
class TablePreferencesRequest extends FormRequest
{
    private ?TableDefinition $resolvedDefinition = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $columnIds = array_keys($this->definition()->defaultColumnLayout());

        return [
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['array'],
            'columns.*.id' => ['required', 'string', Rule::in($columnIds)],
            'columns.*.visible' => ['sometimes', 'boolean'],
            'columns.*.width' => ['sometimes', 'integer', 'min:50', 'max:1000'],
            'columns.*.order' => ['sometimes', 'integer', 'min:0'],
            'product_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')],
        ];
    }

    /**
     * The category tab the client saved from, so the response config carries
     * that tab's `attr.*` columns instead of the unscoped shape. Null = the
     * "Tutte" tab, and every other domain, which never sends it.
     */
    public function productCategoryId(): ?int
    {
        $value = $this->validated('product_category_id');

        return $value === null ? null : (int) $value;
    }

    /**
     * The validated column state as a list of typed DTOs (no magic array crosses
     * into the Service — see standards/architecture.md → Data Transfer Objects).
     *
     * @return array<int, ColumnState>
     */
    public function columnsState(): array
    {
        /** @var array<int, array<string, mixed>> $columns */
        $columns = $this->validated('columns', []);

        return array_map(
            static fn (array $column): ColumnState => ColumnState::fromValidated($column),
            array_values($columns),
        );
    }

    /**
     * Resolve the TableDefinition for the route's {domain}. Unknown domain →
     * ModelNotFoundException → 404 (before any validation runs). Resolved once.
     */
    private function definition(): TableDefinition
    {
        if ($this->resolvedDefinition === null) {
            $domain = (string) $this->route('domain');
            $this->resolvedDefinition = app(TableRegistry::class)->resolve($domain);
        }

        return $this->resolvedDefinition;
    }
}
