import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
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
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import { resolveImportWizardErrorMessage } from '@/features/imports/wizard/resolve-error-message'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Shared, stable callback threaded through `gridOptions.context`
 * (review-grid.tsx) rather than `cellRendererParams`: all 4 geo columns open
 * the same cascade popup and apply through the same mutation, so there is
 * nothing column-specific to drill into each colDef.
 */
export interface ReviewGeoGridContext {
  onApplyGeo: (row: ImportRunRowItem, geo: GeoValue, node: IRowNode<ImportRunRowItem>) => Promise<void>
}

export interface ReviewGeoCellParams extends ICellRendererParams<ImportRunRowItem, string, ReviewGeoGridContext> {
  /** Forces plain text with no popup affordance (spec 0034 AC-013 / spec 0038 AC-013). */
  readOnly?: boolean
}

/** Reads the geo ids the backend has already fused onto the row's `values` (spec 0038). */
function toNullableId(raw: string | number | null | undefined): number | null {
  if (raw === null || raw === undefined || raw === '') return null
  const id = typeof raw === 'number' ? raw : Number(raw)
  return Number.isFinite(id) ? id : null
}

function geoValueFromRow(row: ImportRunRowItem): GeoValue {
  return {
    country_id: toNullableId(row.values.country_id),
    state_id: toNullableId(row.values.state_id),
    province_id: toNullableId(row.values.province_id),
    city_id: toNullableId(row.values.city_id),
  }
}

/**
 * Geo cell (spec 0038 AC-010/AC-013): the text is the column's own resolved
 * name (`valueGetter`), never editable in place. Not `readOnly`, the cell is
 * a button that opens a popup with the full 4-level cascade, precompiled
 * from the row's already-resolved ids — any of the 4 columns opens the same
 * cascade, since a single PATCH saves all 4 levels together.
 */
export function ReviewGeoCell({ data, value, node, context, readOnly }: ReviewGeoCellParams) {
  const { t } = useTranslation('importWizard')
  const [open, setOpen] = useState(false)
  const displayValue = value || '—'

  if (!data) return null

  if (readOnly) {
    return <span className="truncate text-xs">{displayValue}</span>
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <button
          type="button"
          className="block w-full truncate text-left text-xs underline-offset-2 hover:underline"
          aria-label={t('review.geo.editLabel')}
        >
          {displayValue}
        </button>
      </DialogTrigger>
      <DialogContent size="sm">
        <ReviewGeoDialogBody
          row={data}
          node={node}
          onApplyGeo={context.onApplyGeo}
          onClose={() => setOpen(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface ReviewGeoDialogBodyProps {
  row: ImportRunRowItem
  node: IRowNode<ImportRunRowItem>
  onApplyGeo: ReviewGeoGridContext['onApplyGeo']
  onClose: () => void
}

/**
 * The popup's own content: a controlled `GeoSelect` seeded from the row's
 * ids, and the Applica/Annulla actions (spec 0038 AC-011/AC-012/AC-014).
 * Annulla and the dialog's own close affordances never call `onApplyGeo` —
 * only the Applica click does.
 */
function ReviewGeoDialogBody({ row, node, onApplyGeo, onClose }: ReviewGeoDialogBodyProps) {
  const { t } = useTranslation('importWizard')
  const [geo, setGeo] = useState<GeoValue>(() => geoValueFromRow(row))
  const [isApplying, setIsApplying] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Step 1: PATCH the cascade's current 4 ids as a single `geo` block.
  // Step 2: on success, close the popup (the grid row/counts are refreshed
  // by the caller's `onApplyGeo`); on failure, keep it open and surface the
  // error inline so the operator can adjust the selection and retry.
  function handleApply() {
    setIsApplying(true)
    setError(null)
    onApplyGeo(row, geo, node)
      .then(() => onClose())
      .catch((cause: unknown) => setError(resolveImportWizardErrorMessage(cause, t)))
      .finally(() => setIsApplying(false))
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('review.geo.title')}</DialogTitle>
        <DialogDescription>{t('review.geo.description')}</DialogDescription>
      </DialogHeader>

      <GeoSelect value={geo} onChange={setGeo} disabled={isApplying} />

      {error ? (
        <p role="alert" className="text-xs text-destructive">
          {error}
        </p>
      ) : null}

      <DialogFooter>
        <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={isApplying}>
          {t('review.geo.cancel')}
        </Button>
        <Button type="button" size="sm" onClick={handleApply} disabled={isApplying}>
          {t('review.geo.apply')}
        </Button>
      </DialogFooter>
    </>
  )
}
