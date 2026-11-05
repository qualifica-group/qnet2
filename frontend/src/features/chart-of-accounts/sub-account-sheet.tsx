import { CostSubAccountSheet } from '@/features/chart-of-accounts/cost-sub-account-sheet'
import { RevenueSubAccountSheet } from '@/features/chart-of-accounts/revenue-sub-account-sheet'
import { TreasurySubAccountSheet } from '@/features/chart-of-accounts/treasury-sub-account-sheet'
import type { SubAccountSheetState } from '@/features/chart-of-accounts/sheet-state'
import type { ChartGroup } from '@/features/chart-of-accounts/types'

interface SubAccountSheetProps {
  state: SubAccountSheetState
  groups: ChartGroup[]
  onClose: () => void
}

/** Revenue groups own revenue types, financial groups treasury types (spec 0355), cost groups cost types (spec 0348 D-3). */
function isSource(state: SubAccountSheetState, kind: 'revenue' | 'financial'): boolean {
  if (state.type === 'sub-create') return state.group.kind === kind
  return state.sub.source === (kind === 'revenue' ? 'revenue_type' : 'treasury_type')
}

export function SubAccountSheet(props: SubAccountSheetProps) {
  if (isSource(props.state, 'revenue')) return <RevenueSubAccountSheet {...props} />
  if (isSource(props.state, 'financial')) return <TreasurySubAccountSheet {...props} />
  return <CostSubAccountSheet {...props} />
}
