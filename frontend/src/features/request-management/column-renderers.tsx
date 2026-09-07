/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { AlertTriangle, MapPin, Pencil, Radio } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useAbilities } from '@/features/auth/use-abilities'
import { useOpenOfferLines } from '@/features/request-management/offer-lines-dialog-context'
import type { ProductLineCellValue } from '@/features/product-lines/product-lines-cell-editor'
import {
  BADGE_BASE,
  CELL_WRAPPER,
  DateTimeCell,
  EmptyCell,
  badgeColorClass,
} from '@/features/table/cell-renderers'
import { relationLabel } from '@/features/table/relation-label'
import { BooleanBadgeCell, RelationCell, StatusBadgeCell } from '@/features/table/rich-cells'
import { UserCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * A plain text cell: truncates with a native tooltip on overflow and falls
 * back to the shared em-dash EmptyCell when the value is null/empty. Reused by
 * every display-only text column of the request-management worklist
 * (`operator_ga2`, the client anagraphic fields).
 */
function TextCell({ value }: ICellRendererParams) {
  if (value === null || value === undefined || value === '') {
    return <EmptyCell align="left" />
  }
  const text = String(value)
  return (
    <div className="flex h-full items-center overflow-hidden">
      <span className="truncate" title={text}>
        {text}
      </span>
    </div>
  )
}

/**
 * The "Categoria prodotto" cell (spec 0075): the row projects the request's
 * own {funzione aziendale, categoria} pairs — what the inline editor commits —
 * so the cell renders the CATEGORY names out of them, comma-joined, with the
 * full pair list as its native tooltip.
 */
function ProductCategoriesCell({ value }: ICellRendererParams) {
  const pairs = Array.isArray(value) ? (value as ProductLineCellValue[]) : []

  if (pairs.length === 0) {
    return <EmptyCell align="left" />
  }

  const label = pairs.map((pair) => pair.product_category_name).join(', ')
  const tooltip = pairs.map((pair) => `${pair.business_function_name} › ${pair.product_category_name}`).join('\n')

  return (
    <div className="flex h-full items-center overflow-hidden">
      <span className="truncate" title={tooltip}>
        {label}
      </span>
    </div>
  )
}

/**
 * The "Richieste di modifica" cell (spec 0078, AC-037): a count that only
 * matters when it is NOT zero. Above zero it reads as an alert — amber pill,
 * warning icon and the number, with the localized count as its accessible name
 * and native tooltip; at zero (or with no value at all) the cell stays empty,
 * so the operator's eye is drawn only by the rows that actually need handling.
 */
function PendingChangeRequestsCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  const count = typeof value === 'number' && Number.isFinite(value) ? value : 0

  if (count <= 0) {
    return null
  }

  const label = t('requestManagement.pendingChangeRequests.alert', { count })

  return (
    <div className={CELL_WRAPPER}>
      <Badge
        variant="secondary"
        className={cn(BADGE_BASE, 'gap-1 tabular-nums', badgeColorClass('amber'))}
        aria-label={label}
        title={label}
      >
        <AlertTriangle aria-hidden="true" className="size-3.5 shrink-0" />
        {count}
      </Badge>
    </div>
  )
}

/**
 * The "Linee di prodotto" cell (spec 0086 D-7, made quick-editable by the user
 * directive 2026-09-07): the products of the Offerta's own REVENUE rows,
 * comma-joined as `RefNamesCell` renders them, plus a pencil that opens the
 * quick edit of those ROWS — product, quantita', prezzo unitario, IVA — in the
 * dialog `OfferLinesDialogProvider` mounts once for the whole grid.
 *
 * The pencil shows on hover and on keyboard focus, and it shows on an EMPTY
 * cell too: a request with no offer row yet is exactly the one that needs to
 * gain one. It is hidden without `request-management.update`, which is a UI
 * affordance only — the per-record authorization is the endpoint's own
 * (`RequestManagementScope`), and it is what actually decides.
 */
function OfferLinesCell({ value, data }: ICellRendererParams) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { openOfferLines } = useOpenOfferLines()

  const names = Array.isArray(value)
    ? value.map((entry) => relationLabel(entry)).filter((name): name is string => name !== null)
    : []
  const label = names.join(', ')
  const quoteId = typeof data?.id === 'number' ? data.id : null
  const editable = quoteId !== null && can('request-management.update')
  const editLabel = t('requestManagement.offerLines.editAction')

  return (
    <div className="group/offer-lines flex h-full items-center gap-1 overflow-hidden px-2 py-1">
      {names.length === 0 ? (
        <span className="text-muted-foreground">—</span>
      ) : (
        <span className="truncate" title={label}>
          {label}
        </span>
      )}

      {editable && (
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="size-6 shrink-0 opacity-0 transition-opacity group-hover/offer-lines:opacity-100 focus-visible:opacity-100"
          aria-label={editLabel}
          title={editLabel}
          onClick={() => openOfferLines(quoteId)}
        >
          <Pencil className="size-3.5" aria-hidden="true" />
        </Button>
      )}
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0049). `source`
 * ("Fonte", user directive 2026-07-31) is a plain `{id, name}` relation, so it
 * reuses the shared `RelationCell` exactly as the leads grid does for the same
 * entity; `general_notes` is free text, truncated by the local `TextCell` with
 * the full note as its native tooltip. The GA2
 * `operator_ga2` renders as the shared `UserCell` (avatar + hover-card that
 * opens the user's profile Sheet — same component as the opportunities
 * `supervisor` column). `operational_site` (spec 0056) has no `name`, only the
 * server-composed `label`, which `RelationCell` reads. The client's
 * PersonalData anagraphic fields are display-only text, while the product
 * categories render their own pair projection (spec 0075). `next_callback_at`
 * (spec 0052) reuses the shared `DateTimeCell` in its `optionalTime` mode: the
 * hour is optional (user directive 2026-07-31), so a callback planned without
 * one shows as a plain date instead of a misleading "00:00".
 * `quote_workflow_status` (user directive 2026-08-31) reuses the shared
 * `StatusBadgeCell` — the same colored dot + pill the Offerte grid already
 * paints for this very relation.
 */
export const requestManagementColumnRenderers: TableRendererMap = {
  source: (params) => <RelationCell {...params} icon={Radio} />,
  pending_change_requests: (params) => <PendingChangeRequestsCell {...params} />,
  product_categories: (params) => <ProductCategoriesCell {...params} />,
  offer_lines: (params) => <OfferLinesCell {...params} />,
  general_notes: (params) => <TextCell {...params} />,
  operator_ga2: (params) => <UserCell {...params} />,
  operational_site: (params) => <RelationCell {...params} icon={MapPin} />,
  // Spec 0079: a system flag, not auto-mounted by `resolveCellRenderer`
  // (only `type: 'badge'`/`enum` are) — without this row the cell would show
  // the raw boolean instead of the Si/No badge (AC-022).
  is_transferred: (params) => <BooleanBadgeCell {...params} />,
  first_name: (params) => <TextCell {...params} />,
  last_name: (params) => <TextCell {...params} />,
  tax_code: (params) => <TextCell {...params} />,
  phone: (params) => <TextCell {...params} />,
  next_callback_at: (params) => <DateTimeCell {...params} optionalTime />,
  quote_workflow_status: (params) => <StatusBadgeCell {...params} />,
}
