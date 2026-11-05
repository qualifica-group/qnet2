import { CostSubAccountSheet } from '@/features/chart-of-accounts/cost-sub-account-sheet'
import { RevenueSubAccountSheet } from '@/features/chart-of-accounts/revenue-sub-account-sheet'
import type { SubAccountSheetState } from '@/features/chart-of-accounts/sheet-state'
import type { ChartGroup } from '@/features/chart-of-accounts/types'

interface SubAccountSheetProps {
  state: SubAccountSheetState
  groups: ChartGroup[]
  onClose: () => void
}

/** Revenue groups own revenue types, cost groups own cost types (spec 0348 D-3). */
function isRevenue(state: SubAccountSheetState): boolean {
  return state.type === 'sub-create' ? state.group.kind === 'revenue' : state.sub.source === 'revenue_type'
}

export function SubAccountSheet(props: SubAccountSheetProps) {
  return isRevenue(props.state) ? <RevenueSubAccountSheet {...props} /> : <CostSubAccountSheet {...props} />
}
