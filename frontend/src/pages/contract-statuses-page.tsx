import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ContractStatusesTable } from '@/features/contract-statuses/contract-statuses-table'

/**
 * Contract statuses page (spec 0072). Light composition only: gates access
 * with `contract-statuses.viewAny` and mounts the thin Contract Statuses
 * adapter, which in turn mounts the generic table (`domain="contract-statuses"`).
 * The generic table owns config loading and loading/error/empty states; no
 * business logic or data fetching lives here.
 */
export default function ContractStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="contract-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('contractStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ContractStatusesTable />
      </div>
    </Can>
  )
}
