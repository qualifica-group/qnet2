<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use App\Support\ManagerPositions;
use Illuminate\Support\Facades\DB;

/**
 * Which Gestore Account position columns a tab shows, and under which name
 * (spec 0382 D-2/D-3). The catalogue declares every position up to
 * ManagerPositions::MAX; this class trims it to the positions in use and
 * applies the tab's category labels:
 *  - "Tutte", or a category with no label at all: positions 1..N, N = the
 *    highest position occupied in `quote_user` but never below the Operatore
 *    (so a system with no team yet can still assign from the cell), all
 *    visible, "Gestore account N";
 *  - a category with labels: positions up to the higher of N and its last
 *    label; a labelled position takes the label and is visible, the others
 *    keep "Gestore account N" and start hidden (selectable from the chooser).
 *
 * The labels are the category's EFFECTIVE `manager_labels` (spec 0080,
 * inherited from its ancestors), of the Offerta's own team (spec 0087 D-10).
 */
final class RequestManagerPositionLayout
{
    private ?int $highestOccupied = null;

    public function __construct(private readonly ProductCategoryService $categories) {}

    /**
     * The config columns of a tab: position columns trimmed, named and
     * shown or hidden; every other column untouched.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    public function shape(array $columns, ?int $categoryId): array
    {
        // Step 1: the tab's labels and the last position it shows
        $labels = $this->labels($categoryId);
        $lastPosition = max($this->highestOccupied(), $labels === [] ? 0 : max(array_keys($labels)));
        $visibility = $this->visibilityFor($labels);
        $shaped = [];

        // Step 2: drop the positions beyond it, name and show the others
        foreach ($columns as $column) {
            $position = RequestManagerColumns::positionOf((string) ($column['id'] ?? ''));

            if ($position === null) {
                $shaped[] = $column;

                continue;
            }

            if ($position <= $lastPosition) {
                $shaped[] = [
                    ...$column,
                    'label' => $labels[$position] ?? $column['label'],
                    'visible' => $visibility[$column['id']],
                ];
            }
        }

        return $shaped;
    }

    /**
     * The tab's default visibility of every position column, as column id =>
     * visible: the baseline the saved column preferences are a delta of
     * (spec 0382 D-8).
     *
     * @return array<string, bool>
     */
    public function visibility(?int $categoryId): array
    {
        return $this->visibilityFor($this->labels($categoryId));
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<string, bool>
     */
    private function visibilityFor(array $labels): array
    {
        $visibility = [];

        foreach (RequestManagerColumns::positions() as $position => $columnId) {
            $visibility[$columnId] = $labels === [] || isset($labels[$position]);
        }

        return $visibility;
    }

    /**
     * The category's effective labels as position => text, limited to the
     * positions a team can hold. Empty for "Tutte" or a deleted category.
     *
     * @return array<int, string>
     */
    private function labels(?int $categoryId): array
    {
        $category = $categoryId === null ? null : ProductCategory::find($categoryId);

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
