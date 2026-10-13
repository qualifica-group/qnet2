import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CommissionSettlementsTable } from '@/features/commission-settlements/commission-settlements-table'

/**
 * Commission settlements page (Accounting > Agent commissions). Light
 * composition only: gates access with `commission-settlements.viewAny` (what
 * the table endpoint enforces) and mounts the adapter over the generic table.
 */
export default function CommissionSettlementsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="commission-settlements.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('commissionSettlements.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <CommissionSettlementsTable />
      </div>
    </Can>
  )
}
