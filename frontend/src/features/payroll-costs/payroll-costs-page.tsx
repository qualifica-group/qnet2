import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PayrollCostsTable } from '@/features/payroll-costs/payroll-costs-table'

/** Payroll costs page (Accounting > Management control). Gated by `payroll-costs.view`. */
export default function PayrollCostsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="payroll-costs.view"
      fallback={<p className="text-sm text-muted-foreground">{t('payrollCosts.forbidden')}</p>}
    >
      <PayrollCostsTable />
    </Can>
  )
}
