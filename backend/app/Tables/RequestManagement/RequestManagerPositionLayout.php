<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use App\Support\ManagerPositions;
use Illuminate\Support\Facades\DB;

/**
 * Which Gestore Account columns a tab shows, and under which name (spec 0382).
 * The catalogue declares every position up to ManagerPositions::MAX; this
 * class trims it per tab:
 *  - "Tutte", or a category with no label at all (D-2): positions 1..N,
 *    N = the highest position occupied in `quote_user` but never below the
 *    Operatore (so a system with no team yet can still assign from the cell),
 *    "Gestore account N", plus "Gestori account" with the whole team;
 *  - a category with labels (D-11): one column per NAMED position, under its
 *    label, plus "Altri gestori account" with the people in every other
 *    position (RequestOtherManagersColumn).
 *
 * The labels are the category's EFFECTIVE `manager_labels` (spec 0080,
 * inherited from its ancestors), of the Offerta's own team (spec 0087 D-10).
 */
final class RequestManagerPositionLayout
{
    /** The `managers` header in a category tab with labels (spec 0382 D-11). */
    public const string OTHER_MANAGERS_LABEL = 'requestManagement.columns.otherManagers';

    private ?int $highestOccupied = null;

    /** @var array<int, array<int, string>> */
    private array $labelsByCategory = [];

    public function __construct(private readonly ProductCategoryService $categories) {}

    /**
     * The config columns of a tab: position columns trimmed and named, the
     * team column renamed when it holds only the unnamed positions; every
     * other column untouched.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    public function shape(array $columns, ?int $categoryId): array
    {
        // Step 1: the tab's labels, and the positions it shows
        $labels = $this->labels($categoryId);
        $shown = $labels === [] ? range(1, $this->highestOccupied()) : array_keys($labels);
        $shaped = [];

        // Step 2: keep and name the shown positions, rename the team column
        foreach ($columns as $column) {
            $id = (string) ($column['id'] ?? '');
            $position = RequestManagerColumns::positionOf($id);

            if ($position === null) {
                $shaped[] = $id === RequestManagerColumns::MANAGERS_COLUMN_ID && $labels !== []
                    ? [...$column, 'label' => self::OTHER_MANAGERS_LABEL]
                    : $column;

                continue;
            }

            if (in_array($position, $shown, true)) {
                $shaped[] = [...$column, 'label' => $labels[$position] ?? $column['label']];
            }
        }

        return $shaped;
    }

    /**
     * The positions a tab shows as their own column under a category label;
     * empty for "Tutte" and for a category with no label.
     *
     * @return array<int, int>
     */
    public function namedPositions(?int $categoryId): array
    {
        return array_keys($this->labels($categoryId));
    }

    /**
     * The category's effective labels as position => text, limited to the
     * positions a team can hold. Empty for "Tutte" or a deleted category.
     *
     * @return array<int, string>
     */
    private function labels(?int $categoryId): array
    {
        if ($categoryId === null) {
            return [];
        }

        return $this->labelsByCategory[$categoryId] ??= $this->resolveLabels($categoryId);
    }

    /**
     * @return array<int, string>
     */
    private function resolveLabels(int $categoryId): array
    {
        $category = ProductCategory::find($categoryId);

        if ($category === null) {
            return [];
        }

        $labels = [];

        foreach ($this->categories->effectiveManagerLabels($category) as $position => $label) {
            $position = (int) $position;

            if ($position >= 1 && $position <= ManagerPositions::MAX && is_string($label) && trim($label) !== '') {
                $labels[$position] = $label;
            }
        }

        ksort($labels);

        return $labels;
    }

    private function highestOccupied(): int
    {
        return $this->highestOccupied ??= min(
            max((int) DB::table('quote_user')->max('position'), ManagerPositions::OPERATOR),
            ManagerPositions::MAX,
        );
    }
}
