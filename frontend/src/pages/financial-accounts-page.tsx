import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { FinancialAccountsTable } from '@/features/financial-accounts/financial-accounts-table'

/**
 * Financial accounts page. Light composition only: gates access with
 * `financial-accounts.viewAny` and mounts the thin adapter over the generic
 * table (`domain="financial-accounts"`).
 */
export default function FinancialAccountsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="financial-accounts.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('financialAccounts.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <FinancialAccountsTable />
      </div>
    </Can>
  )
}
