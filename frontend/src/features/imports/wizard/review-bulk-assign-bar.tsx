import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { ChevronDown, Package, UserCog } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useRequiredCategories } from '@/features/assignment/use-required-categories'
import type { RequiredCategoriesPayload } from '@/features/assignment/types'
import {
  AssignOperatorsDialog,
  type AssignOperatorsDialogInput,
} from '@/features/leads/assign-operators-dialog'
import { ReviewBulkProductsDialog } from '@/features/imports/wizard/review-bulk-products-dialog'

/**
 * Selection shape consumed by the bar, mirroring AG Grid's own server-side
 * selection state (`gridApi.getServerSideSelectionState()`): `selectAll:
 * false` — `toggledNodes` are the selected (included) row ids; `selectAll:
 * true` — `toggledNodes` are the deselected (excluded) row ids.
 */
export interface ReviewBulkSelectionState {
  selectAll: boolean
  toggledNodes: string[]
}

/**
 * Maps the SSRM selection onto the body of `POST /assignment/required-categories`
 * (spec 0110 AC-041), with the very same `select_all`/`row_ids` semantics as
 * `buildBulkAssignPayload`: the operator picker is then filtered on exactly
 * the rows the assignment is about to target.
 */
function buildRequiredCategoriesSelection(
  selection: ReviewBulkSelectionState,
  importRunId: number,
): RequiredCategoriesPayload {
  return {
    domain: 'import_rows',
    import_run_id: importRunId,
    select_all: selection.selectAll,
    row_ids: selection.toggledNodes.map(Number),
  }
}

export interface ReviewBulkAssignBarProps {
  selection: ReviewBulkSelectionState
  /** The run the selected rows belong to; scopes the competence lookup (spec 0110). */
  importRunId: number
  /**
   * Total staged rows in the run. The selection state above carries no total
   * of its own, so it is the only way to approximate a selection count while
   * `selectAll` is true (documented approximation, spec 0048 — the review
   * grid has no cheaper source of truth than the run's own row count).
   */
  totalRows: number
  /**
   * The Sede to precompile in the operators popup (spec 0048 AC-031), when
   * the caller could cheaply determine the current selection shares one —
   * `null` otherwise (mixed sites, any unset, or a `selectAll` selection).
   */
  defaultSiteId?: number | null
  /** Scopes the products popup's picker exactly like `ProductsOfInterestField`/the per-row popup. */
  campaignCategoryIds: number[]
  /** PATCHes the combined bulk operator/site assignment; rejects (already toasted by the caller) on failure. */
  onAssign: (input: AssignOperatorsDialogInput) => Promise<void>
  /** PATCHes the combined bulk products assignment; rejects (surfaced inline by the popup) on failure. */
  onAssignProducts: (productIds: number[]) => Promise<void>
}

function resolveSelectionLabel(selection: ReviewBulkSelectionState, t: TFunction): string {
  if (selection.selectAll) {
    return selection.toggledNodes.length > 0
      ? t('review.bulkAssign.allExcept', { count: selection.toggledNodes.length })
      : t('review.bulkAssign.all')
  }
  return t('review.bulkAssign.count', { count: selection.toggledNodes.length })
}

/** See `ReviewBulkAssignBarProps.totalRows`. */
function resolveSelectionCount(selection: ReviewBulkSelectionState, totalRows: number): number {
  if (!selection.selectAll) {
    return selection.toggledNodes.length
  }
  return Math.max(totalRows - selection.toggledNodes.length, 0)
}

/**
 * Compact toolbar shown above the review grid only while the SSRM selection
 * is non-empty: the selection count (or "All") and a single "Azioni" dropdown
 * (client directive 2026-07-21: never a row of loose buttons, mirrors
 * `use-bulk-actions-slot.tsx`'s pattern) with two entries — "Assegna
 * operatori", opening the SAME popup the Lead table uses (spec 0048 AC-050),
 * and "Assegna prodotti" (spec 0094 bulk delta), opening a dedicated
 * assign-only products popup.
 */
export function ReviewBulkAssignBar({
  selection,
  importRunId,
  totalRows,
  defaultSiteId,
  campaignCategoryIds,
  onAssign,
  onAssignProducts,
}: ReviewBulkAssignBarProps) {
  const { t } = useTranslation('importWizard')
  const [operatorsOpen, setOperatorsOpen] = useState(false)
  const [productsOpen, setProductsOpen] = useState(false)
  const selectionCount = resolveSelectionCount(selection, totalRows)

  // Competence filter of the popup's Operatore picker (spec 0110 AC-041),
  // resolved only while the popup is open: a selection on its own — which
  // changes on every checkbox toggle — must not hit the endpoint.
  const { competenceCategoryIds, isResolving } = useRequiredCategories({
    selection: buildRequiredCategoriesSelection(selection, importRunId),
    enabled: operatorsOpen,
  })

  return (
    <div
      className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 px-2.5 py-1.5 text-xs"
      role="toolbar"
      aria-label={t('review.bulkAssign.toolbarLabel')}
    >
      <span className="font-medium">{resolveSelectionLabel(selection, t)}</span>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button type="button" variant="secondary" size="sm" className="h-7 gap-1.5 px-2.5 text-xs">
            {t('review.bulkAssign.actionsLabel', { count: selectionCount })}
            <ChevronDown className="size-3.5" aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onSelect={() => setOperatorsOpen(true)}>
            <UserCog className="size-3.5" aria-hidden="true" />
            {t('review.bulkAssign.assign')}
          </DropdownMenuItem>
          <DropdownMenuItem onSelect={() => setProductsOpen(true)}>
            <Package className="size-3.5" aria-hidden="true" />
            {t('review.bulkAssign.products.menuLabel')}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AssignOperatorsDialog
        open={operatorsOpen}
        onOpenChange={setOperatorsOpen}
        selectionCount={selectionCount}
        defaultSiteId={defaultSiteId}
        competenceCategoryIds={competenceCategoryIds}
        isResolvingCompetence={isResolving}
        onAssign={onAssign}
      />

      <ReviewBulkProductsDialog
        open={productsOpen}
        onOpenChange={setProductsOpen}
        selectionCount={selectionCount}
        campaignCategoryIds={campaignCategoryIds}
        onApply={onAssignProducts}
      />
    </div>
  )
}
