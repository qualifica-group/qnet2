import { Briefcase, Building, Building2, Handshake, MapPin, UserRound } from 'lucide-react'
import type { ICellRendererParams } from 'ag-grid-community'
import { CELL_WRAPPER, DateTimeCell } from '@/features/table/cell-renderers'
import { CodeBadgeCell, CurrencyCell, RelationCell, StatusBadgeCell } from '@/features/table/rich-cells'
import { UserCell, UserStackCell } from '@/features/table/user-cell'
import { QuoteAlertBadge } from '@/features/quotes/quote-alert-badge'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { QuoteAlert } from '@/features/quotes/types'

/** The `alert` column cell (spec 0102 D-4): centered like every other badge cell, mirrors `contracts/column-renderers.tsx`'s `AlertCell`. */
function AlertCell({ value }: ICellRendererParams) {
  const alert = (value ?? null) as QuoteAlert
  if (!alert) {
    return null
  }
  return (
    <div className={CELL_WRAPPER}>
      <QuoteAlertBadge alert={alert} />
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0065
 * `data_contract`), built from the shared cross-module cell library so the
 * quotes grid matches every other module (relation + icon, colored status
 * pill, money, avatar). `title` falls back to the AG Grid default text cell;
 * `code` renders as a compact monospace badge (mirrors `projects`/`products`);
 * `opportunity`/`commercial`/`reporter` are `{id, name}` relations (D-3:
 * commercial/reporter are a snapshot FK to `referents`, like on Opportunities);
 * `supervisor` is a `users` FK, rendered as a person avatar+name (mirrors
 * `opportunityColumnRenderers.supervisor`); `revenue_net`/`cost_net`/
 * `margin_net` are the persisted aggregates (D-9); `created_at` reuses the
 * shared datetime renderer. `company`/`company_site`/`operational_site`
 * (directive 2026-07-30) are relations too — the last one projected as
 * `{id, label}` (the site has no name), the exact shape
 * `opportunityColumnRenderers.operational_site` already renders with the same
 * `RelationCell`. `managers` (spec 0087) is the offer's own G.A. avatar
 * stack, appended last — the exact `UserStackCell` renderer
 * `opportunityColumnRenderers.managers` already uses. `next_callback_at`
 * (user directive 2026-09-04, migrated off the Opportunity) reuses the SAME
 * `optionalTime` datetime cell Gestione Richieste renders it with, so the
 * planned callback reads identically in both grids. `alert` (spec 0102 D-4)
 * is the calculated missing-offer-lines indicator, appended last
 * (append-only convention, spec 0001).
 */
export const quoteColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  opportunity: (params) => <RelationCell {...params} icon={Handshake} />,
  quote_workflow_status: (params) => <StatusBadgeCell {...params} />,
  commercial: (params) => <RelationCell {...params} icon={Briefcase} />,
  reporter: (params) => <RelationCell {...params} icon={UserRound} />,
  supervisor: (params) => <UserCell {...params} />,
  managers: (params) => <UserStackCell {...params} />,
  revenue_net: (params) => <CurrencyCell {...params} />,
  cost_net: (params) => <CurrencyCell {...params} />,
  margin_net: (params) => <CurrencyCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  company: (params) => <RelationCell {...params} icon={Building2} />,
  company_site: (params) => <RelationCell {...params} icon={Building} />,
  operational_site: (params) => <RelationCell {...params} icon={MapPin} />,
  next_callback_at: (params) => <DateTimeCell {...params} optionalTime />,
  alert: (params) => <AlertCell {...params} />,
}
