import { groupSubAccounts, subAccountLabel } from '@/features/chart-of-accounts/sub-account-select/sub-account-groups'
import type { RevenueTypeGroup, RevenueTypeOption } from '@/features/revenue-types/types'

/** Option text: code first, so the list reads like the chart of accounts. */
export function revenueTypeLabel(option: Pick<RevenueTypeOption, 'code' | 'name'>): string {
  return subAccountLabel(option)
}

/** Groups options by mastro > conto keeping the server order (natural code order). */
export function groupRevenueTypes(options: readonly RevenueTypeOption[]): RevenueTypeGroup[] {
  return groupSubAccounts(options, '')
}
