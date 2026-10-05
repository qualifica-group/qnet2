import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Badge } from '@/components/ui/badge'
import { BADGE_BASE, CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import { FINANCIAL_ACCOUNT_TYPES, type FinancialAccountType } from '@/features/financial-accounts/types'

function isAccountType(value: unknown): value is FinancialAccountType {
  return FINANCIAL_ACCOUNT_TYPES.some((type) => type === value)
}

/** Renders the raw `type` value (bank_account|card|cash) as its translated label. */
export function FinancialAccountTypeCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  if (!isAccountType(value)) {
    return <EmptyCell />
  }

  return (
    <div className={CELL_WRAPPER}>
      <Badge variant="secondary" className={BADGE_BASE}>
        {t(`financialAccounts.types.${value}`)}
      </Badge>
    </div>
  )
}
