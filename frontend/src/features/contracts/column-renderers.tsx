/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import { Briefcase, Building2, Handshake, UserRound } from 'lucide-react'
import type { ICellRendererParams } from 'ag-grid-community'
import { CELL_WRAPPER } from '@/features/table/cell-renderers'
import { CodeBadgeCell, CurrencyCell, DateCell, RelationCell, StatusBadgeCell } from '@/features/table/rich-cells'
import { UserCell, UserStackCell } from '@/features/table/user-cell'
import { ContractAlertBadge } from '@/features/contracts/contract-status-badges'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { ContractAlert } from '@/features/contracts/types'

/** The `alert` column cell: the same expiring/renewal_due badge as the detail hero, centered like every other badge cell. */
function AlertCell({ value }: ICellRendererParams) {
  const alert = (value ?? null) as ContractAlert
  if (!alert) {
    return null
  }
  return (
    <div className={CELL_WRAPPER}>
      <ContractAlertBadge alert={alert} />
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0072
 * `data_contract`, AC-029). `code`/`title` show the underlying QUOTE's code
 * (D-1: contracts have no code of their own); `registry`/`opportunity`/
 * `commercial`/`reporter` are `{id, name}` relations (mirrors
 * `opportunityColumnRenderers`); `supervisor` a `users` FK rendered as a
 * person avatar+name; `managers` (user directive 2026-08-31) is the offer's
 * G.A. avatar stack, the exact `UserStackCell` the Offerte grid already uses;
 * `contract_status` reuses the shared `StatusBadgeCell`
 * unchanged (team directive); `quote_date`/`accepted_at`/`validated_at`/
 * `renewal_date`/`expiry_date`/`terminated_at` are DATE (no time part)
 * columns; `revenue_net`/`revenue_vat`/`revenue_gross` are the persisted
 * quote aggregates; `alert` is the calculated D-4 indicator.
 */
export const contractColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  registry: (params) => <RelationCell {...params} icon={Building2} />,
  opportunity: (params) => <RelationCell {...params} icon={Handshake} />,
  commercial: (params) => <RelationCell {...params} icon={Briefcase} />,
  reporter: (params) => <RelationCell {...params} icon={UserRound} />,
  supervisor: (params) => <UserCell {...params} />,
  managers: (params) => <UserStackCell {...params} />,
  contract_status: (params) => <StatusBadgeCell {...params} />,
  quote_date: (params) => <DateCell {...params} />,
  accepted_at: (params) => <DateCell {...params} />,
  validated_at: (params) => <DateCell {...params} />,
  renewal_date: (params) => <DateCell {...params} />,
  expiry_date: (params) => <DateCell {...params} />,
  terminated_at: (params) => <DateCell {...params} />,
  revenue_net: (params) => <CurrencyCell {...params} />,
  revenue_vat: (params) => <CurrencyCell {...params} />,
  revenue_gross: (params) => <CurrencyCell {...params} />,
  alert: (params) => <AlertCell {...params} />,
}
