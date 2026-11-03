import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ChartOfAccountsScreen } from '@/features/chart-of-accounts/chart-of-accounts-screen'

/**
 * Chart of accounts page (spec 0348). Light composition only: gates access with
 * `chart-accounts.view`; the tree, its state and its actions live in the feature.
 */
export function ChartOfAccountsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="chart-accounts.view"
      fallback={<p className="text-sm text-muted-foreground">{t('chartOfAccounts.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ChartOfAccountsScreen />
      </div>
    </Can>
  )
}

export default ChartOfAccountsPage
