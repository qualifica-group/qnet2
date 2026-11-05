import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { ForSelectItem } from '@/features/for-select/types'
import type { CostSubAccountItem } from '@/features/chart-of-accounts/sub-account-select/cost-sub-account'
import { SubAccountGroupedList } from '@/features/chart-of-accounts/sub-account-select/sub-account-grouped-list'
import { groupSubAccounts } from '@/features/chart-of-accounts/sub-account-select/sub-account-groups'

interface CostSubAccountGroupsProps {
  items: ForSelectItem[]
  renderRow: (item: ForSelectItem) => ReactNode
}

/** The loaded cost options under mastro > conto headings, unplaced ones in a trailing "to be placed" group. */
export function CostSubAccountGroups({ items, renderRow }: CostSubAccountGroupsProps) {
  const { t } = useTranslation()
  const groups = groupSubAccounts<ForSelectItem & Partial<CostSubAccountItem>>(items, t('chartOfAccounts.unplacedGroup'))
  return <SubAccountGroupedList groups={groups} renderOption={renderRow} />
}
