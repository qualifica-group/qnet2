import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CommissionAccrualsTable } from '@/features/commission-accruals/commission-accruals-table'

/**
 * Commission accruals page (Accounting > Agent commissions). Light
 * composition only: gates access with `commission-accruals.viewAny` (what the
 * table endpoint enforces) and mounts the read-only adapter over the generic
 * table (`domain="commission-accruals"`).
 */
export default function CommissionAccrualsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="commission-accruals.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('commissionAccruals.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <CommissionAccrualsTable />
      </div>
    </Can>
  )
}
