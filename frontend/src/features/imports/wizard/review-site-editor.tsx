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
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { resolveImportWizardErrorMessage } from '@/features/imports/wizard/resolve-error-message'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Shared, stable state/callback threaded through `gridOptions.context`
 * (review-grid.tsx), mirroring `ReviewOperatorGridContext`: the site column
 * opens a popup and applies through the same mutation regardless of which
 * row triggered it. Unlike operator, the operational site has NO
 * global-config default (it is per-row-only) — `globalDefaultSiteId` is
 * always `null`, kept only so the cell/dialog mirror the operator shape 1:1.
 */
export interface ReviewSiteGridContext {
  onApplySite: (row: ImportRunRowItem, siteId: number | null, node: IRowNode<ImportRunRowItem>) => Promise<void>
  globalDefaultSiteId: number | null
}

export interface ReviewSiteCellParams
  extends ICellRendererParams<ImportRunRowItem, unknown, ReviewSiteGridContext> {
  /** Forces plain text with no popup affordance, mirroring `ReviewOperatorCell`. */
  readOnly?: boolean
}

/**
 * Per-row operational-site override cell: shows the row's own
 * `operational_site` when set, otherwise an em dash (there is no run default
 * to fall back to, so `context.globalDefaultSiteId` is always `null` and the
 * "uses the default" hint never applies). Not `readOnly`, the cell is a
 * button opening a popup with a site picker (`AsyncPaginatedSelect`)
 * precompiled from the row's current override.
 */
export function ReviewSiteCell({ data, node, context, readOnly }: ReviewSiteCellParams) {
  const { t } = useTranslation('importWizard')
  const [open, setOpen] = useState(false)

  if (!data) return null

  const displayValue =
    data.operational_site?.name ?? (context.globalDefaultSiteId != null ? t('review.site.usingDefault') : '—')

  if (readOnly) {
    return <span className="truncate text-xs text-muted-foreground">{displayValue}</span>
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <button
          type="button"
          className="block w-full truncate text-left text-xs underline-offset-2 hover:underline"
          aria-label={t('review.site.editLabel')}
        >
          {displayValue}
        </button>
      </DialogTrigger>
      <DialogContent size="sm">
        <ReviewSiteDialogBody
          row={data}
          node={node}
          onApplySite={context.onApplySite}
          onClose={() => setOpen(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface ReviewSiteDialogBodyProps {
  row: ImportRunRowItem
  node: IRowNode<ImportRunRowItem>
  onApplySite: ReviewSiteGridContext['onApplySite']
  onClose: () => void
}

/**
 * The popup's own content: a controlled `AsyncPaginatedSelect` seeded from
 * the row's current override, a "clear" shortcut clearing it locally, and the
 * Applica/Annulla actions (mirrors `ReviewOperatorDialogBody`). Annulla and
 * the dialog's own close affordances never call `onApplySite` — only the
 * Applica click does.
 */
function ReviewSiteDialogBody({ row, node, onApplySite, onClose }: ReviewSiteDialogBodyProps) {
  const { t } = useTranslation('importWizard')
  const [siteId, setSiteId] = useState<number | null>(row.operational_site_id)
  const [isApplying, setIsApplying] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Step 1: PATCH the popup's current site id (or `null` to clear the
  // override) as `operational_site_id`. Step 2: on success, close the popup
  // (the grid row/counts are refreshed by the caller's `onApplySite`); on
  // failure, keep it open and surface the error inline so the operator can
  // adjust the selection and retry.
  function handleApply() {
    setIsApplying(true)
    setError(null)
    onApplySite(row, siteId, node)
      .then(() => onClose())
      .catch((cause: unknown) => setError(resolveImportWizardErrorMessage(cause, t)))
      .finally(() => setIsApplying(false))
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('review.site.title')}</DialogTitle>
        <DialogDescription>{t('review.site.description')}</DialogDescription>
      </DialogHeader>

      <AsyncPaginatedSelect
        resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
        value={siteId}
        onChange={setSiteId}
        selectedItem={
          row.operational_site ? { id: row.operational_site.id, label: row.operational_site.name } : null
        }
        disabled={isApplying}
        labels={{
          placeholder: t('review.site.placeholder'),
          searchPlaceholder: t('review.site.searchPlaceholder'),
          empty: t('review.site.empty'),
          error: t('review.site.selectError'),
          clearLabel: t('review.site.selectClear'),
          triggerLabel: t('review.site.title'),
          retry: t('review.site.retry'),
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
          onClick={() => setSiteId(null)}
          disabled={isApplying || siteId === null}
        >
          {t('review.site.clear')}
        </Button>
        <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={isApplying}>
          {t('review.site.cancel')}
        </Button>
        <Button type="button" size="sm" onClick={handleApply} disabled={isApplying}>
          {t('review.site.apply')}
        </Button>
      </DialogFooter>
    </>
  )
}
