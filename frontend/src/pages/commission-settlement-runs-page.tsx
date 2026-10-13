import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CommissionSettlementRunsTable } from '@/features/commission-settlement-runs/commission-settlement-runs-table'

/**
 * Settlement runs page (Accounting > Agent commissions). Light composition
 * only: gates access with `commission-settlements.viewAny` (runs are not a
 * permission resource of their own) and mounts the thin table adapter.
 */
export default function CommissionSettlementRunsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="commission-settlements.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('commissionSettlementRuns.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <CommissionSettlementRunsTable />
      </div>
    </Can>
  )
}
