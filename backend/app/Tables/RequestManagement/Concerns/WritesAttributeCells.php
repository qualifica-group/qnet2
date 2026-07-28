<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement\Concerns;

use App\Models\Opportunity;
use App\Models\User;
use App\Tables\RequestManagement\AttributeColumnBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

/**
 * Inline cell-editing for `attr.<code>` columns (spec 0064 §M4): split out of
 * `AttributeScopedTableDefinition` to stay within the file-size budget
 * (engineering.md §6) — mirrors the sibling
 * `App\Tables\RequestManagement\Concerns\WritesInlineEditableCells` (the
 * INNER definition's own spec 0054 write path).
 *
 * The using class must expose `private readonly TableDefinition $inner`,
 * `private readonly AttributeColumnBuilder $columnBuilder`,
 * `private readonly AuthorizationRegistry $authorizationRegistry`,
 * `private readonly RequestManagementService $service`, a private
 * `categoryAttributes(): Collection` method and a private
 * `applicableAttributes(Opportunity): Collection` method, plus a settable
 * `?Collection $rowAttributesOverride` property (all declared there, not
 * here, to avoid a readonly-modifier conflict — mirrors
 * `DelegatesUnaugmentedTableMethods`'s documented convention for `$inner`).
 */
trait WritesAttributeCells
{
    /**
     * @return array<int, string>
     */
    public function editableColumnIds(User $actor): array
    {
        $ids = $this->inner->editableColumnIds($actor);
        $attributes = $this->categoryAttributes();

        if ($attributes->isEmpty() || ! $this->attributeValuesEditable($actor)) {
            return $ids;
        }

        return [...$ids, ...$attributes->map(fn (array $row): string => $this->columnBuilder->id($row))->all()];
    }

    /**
     * `attr.<code>` writes go through `RequestManagementService::updateWork()`
     * (spec 0064 §M4) — never a direct `attribute_values` write — so
     * validation/normalization/merge/activity-log stay the SAME single choke
     * point PATCH and the work panel share. Independent of any scope: the
     * row's OWN applicable-attribute set is what `updateWork()` validates
     * against (AttributeValueValidator), not the tab the client happened to
     * be on.
     *
     * `TableCellUpdateService` (D-1, spec 0054) ALREADY remapped `$columnId`
     * from the client's submitted `attr.<code>` to the raw declaration's
     * `editableField` (`AttributeColumnBuilder::EDITABLE_FIELD`) before ever
     * calling this method — the SAME convention every relation column uses
     * (e.g. `operator_ga2` -> `operator_id`) — so the real code is recovered
     * from the ambient request's own `column` input, mirroring how the
     * sibling `WritesInlineEditableCells::updateCell()` reads `note` the same
     * way (both live within the one authenticated HTTP request the PATCH
     * endpoint handles).
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        if ($columnId !== AttributeColumnBuilder::EDITABLE_FIELD) {
            return $this->inner->updateCell($row, $columnId, $value);
        }

        $code = $this->columnBuilder->codeFor((string) request()->input('column'));

        if ($code === null) {
            return $this->inner->updateCell($row, $columnId, $value);
        }

        /** @var Opportunity $row */
        /** @var User $actor */
        $actor = Auth::user();

        $result = $this->service->updateWork($row, $actor, ['attribute_values' => [$code => $value]]);
        $opportunity = $result['opportunity'];

        $this->rowAttributesOverride = $this->applicableAttributes($opportunity);

        return $opportunity;
    }

    /**
     * `request-management.update` AND `attribute_values` editable in the
     * `role_field_permissions` matrix (spec 0064 contract) — the SAME
     * combined ceiling+DB-config check `ResolvesEditableColumns`/
     * `TableCellUpdateService::assertFieldEditable()` apply for every other
     * native column, resolved directly here since `attr.*` columns are not
     * part of `columnsWithDefaultId()`.
     */
    private function attributeValuesEditable(User $actor): bool
    {
        try {
            $authorization = $this->authorizationRegistry->resolve('request-management');
        } catch (ModelNotFoundException) {
            return false;
        }

        return $authorization->fieldPermissions($actor, new Opportunity)['attribute_values']->editable ?? false;
    }
}
