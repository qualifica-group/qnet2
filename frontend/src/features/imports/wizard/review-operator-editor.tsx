import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { useAssignmentScope } from '@/features/assignment/use-assignment-scope'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { resolveImportWizardErrorMessage } from '@/features/imports/wizard/resolve-error-message'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Query params of the row's operator picker: the Sede the row's campaign
 * resolves to plus, when the row expresses one, its competence requirement
 * (spec 0113 AC-032). `undefined` site = scope not resolved yet or the lookup
 * failed: the picker is disabled anyway and no unfiltered list must be
 * preloaded (AC-034). `null` site = the campaign carries no Sede, so only
 * competence filters.
 */
function buildRowOperatorParams(
  siteId: number | null | undefined,
  competenceCategoryIds: number[] | undefined,
): Record<string, number | number[]> | undefined {
  if (siteId === undefined) {
    return undefined
  }
  const params: Record<string, number | number[]> = {}
  if (siteId !== null) {
    params.operational_site_id = siteId
  }
  if (competenceCategoryIds !== undefined) {
    params.competence_category_ids = competenceCategoryIds
  }
  return Object.keys(params).length === 0 ? undefined : params
}

/**
 * Shared, stable state/callback threaded through `gridOptions.context`
 * (review-grid.tsx), mirroring `ReviewGeoGridContext`: the operator column
 * opens a popup and applies through the same mutation regardless of which
 * row triggered it, so there is nothing column-specific to drill into the
 * colDef. `globalDefaultOperatorId` is the run's own `global_config`
 * `operator_id` (or `null` when the run has no global default), read once at
 * the grid level instead of prop-drilled per column.
 */
export interface ReviewOperatorGridContext {
  onApplyOperator: (
    row: ImportRunRowItem,
    operatorId: number | null,
    node: IRowNode<ImportRunRowItem>,
  ) => Promise<void>
  globalDefaultOperatorId: number | null
  /** The run the rows belong to; scopes the per-row assignment lookup (spec 0110 AC-042, 0113 AC-032). */
  importRunId: number
}

export interface ReviewOperatorCellParams
  extends ICellRendererParams<ImportRunRowItem, unknown, ReviewOperatorGridContext> {
  /** Forces plain text with no popup affordance, mirroring `ReviewGeoCell` (spec 0034 AC-013). */
  readOnly?: boolean
}

/**
 * Per-row operator override cell: shows the row's own `operator` when set;
 * otherwise, a muted "uses the default" hint ONLY when the run actually has
 * a global default operator configured (`context.globalDefaultOperatorId`)
 * — an empty placeholder when it does not, since there is then no default to
 * fall back to. Not `readOnly`, the cell is a button opening a popup with an
 * operator picker (`AsyncPaginatedSelect`) precompiled from the row's
 * current override.
 */
export function ReviewOperatorCell({ data, node, context, readOnly }: ReviewOperatorCellParams) {
  const { t } = useTranslation('importWizard')
  const [open, setOpen] = useState(false)

  if (!data) return null

  const displayValue =
    data.operator?.name ?? (context.globalDefaultOperatorId != null ? t('review.operator.usingDefault') : '—')

  if (readOnly) {
    return <span className="truncate text-xs text-muted-foreground">{displayValue}</span>
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <button
          type="button"
          className="block w-full truncate text-left text-xs underline-offset-2 hover:underline"
          aria-label={t('review.operator.editLabel')}
        >
          {displayValue}
        </button>
      </DialogTrigger>
      <DialogContent size="sm">
        <ReviewOperatorDialogBody
          row={data}
          node={node}
          importRunId={context.importRunId}
          onApplyOperator={context.onApplyOperator}
          onClose={() => setOpen(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface ReviewOperatorDialogBodyProps {
  row: ImportRunRowItem
  node: IRowNode<ImportRunRowItem>
  importRunId: number
  onApplyOperator: ReviewOperatorGridContext['onApplyOperator']
  onClose: () => void
}

/**
 * The popup's own content: a controlled `AsyncPaginatedSelect` seeded from
 * the row's current override, a "use default" shortcut clearing it locally,
 * and the Applica/Annulla actions (mirrors `ReviewGeoDialogBody`). Annulla
 * and the dialog's own close affordances never call `onApplyOperator` — only
 * the Applica click does.
 */
function ReviewOperatorDialogBody({
  row,
  node,
  importRunId,
  onApplyOperator,
  onClose,
}: ReviewOperatorDialogBodyProps) {
  const { t } = useTranslation('importWizard')
  const [operatorId, setOperatorId] = useState<number | null>(row.operator_id)
  const [isApplying, setIsApplying] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Sede + competence of THIS row (spec 0110 AC-042, 0113 AC-032). Radix
  // unmounts the dialog's subtree while closed, so this body — and its lookup
  // — only exists while the popup is open: one request per opened cell, never
  // one per rendered row.
  const { competenceCategoryIds, operationalSiteId, isResolving } = useAssignmentScope({
    selection: { domain: 'import_rows', import_run_id: importRunId, select_all: false, row_ids: [row.id] },
  })

  const operatorParams = buildRowOperatorParams(operationalSiteId, competenceCategoryIds)

  // Step 1: PATCH the popup's current operator id (or `null` to revert to
  // the run default) as `operator_id`. Step 2: on success, close the popup
  // (the grid row/counts are refreshed by the caller's `onApplyOperator`); on
  // failure, keep it open and surface the error inline so the operator can
  // adjust the selection and retry.
  function handleApply() {
    setIsApplying(true)
    setError(null)
    onApplyOperator(row, operatorId, node)
      .then(() => onClose())
      .catch((cause: unknown) => setError(resolveImportWizardErrorMessage(cause, t)))
      .finally(() => setIsApplying(false))
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('review.operator.title')}</DialogTitle>
        <DialogDescription>{t('review.operator.description')}</DialogDescription>
      </DialogHeader>

      <AsyncPaginatedSelect
        resource={USERS_FOR_SELECT_RESOURCE}
        value={operatorId}
        onChange={setOperatorId}
        selectedItem={row.operator ? { id: row.operator.id, label: row.operator.name } : null}
        disabled={isApplying || isResolving || operationalSiteId === undefined}
        showAvatar
        params={operatorParams}
        labels={{
          placeholder: t('review.operator.placeholder'),
          searchPlaceholder: t('review.operator.searchPlaceholder'),
          empty: operatorParams ? t('review.operator.emptyEnabled') : t('review.operator.empty'),
          error: t('review.operator.selectError'),
          clearLabel: t('review.operator.selectClear'),
          triggerLabel: t('review.operator.title'),
          retry: t('review.operator.retry'),
        }}
      />

      {error ? (
        <p role="alert" className="text-xs text-destructive">
          {error}
        </p>
      ) : null}

      <DialogFooter>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="mr-auto"
          onClick={() => setOperatorId(null)}
          disabled={isApplying || operatorId === null}
        >
          {t('review.operator.useDefault')}
        </Button>
        <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={isApplying}>
          {t('review.operator.cancel')}
        </Button>
        <Button type="button" size="sm" onClick={handleApply} disabled={isApplying}>
          {t('review.operator.apply')}
        </Button>
      </DialogFooter>
    </>
  )
}
