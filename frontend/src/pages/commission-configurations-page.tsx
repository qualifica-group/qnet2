import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CommissionConfigurationsTable } from '@/features/commission-configurations/commission-configurations-table'

export default function CommissionConfigurationsPage() {
  const { t } = useTranslation()
  return (
    <Can permission="commission-configurations.viewAny" fallback={<p className="text-sm text-muted-foreground">{t('commissionConfigurations.forbidden')}</p>}>
      <CommissionConfigurationsTable />
    </Can>
  )
}
