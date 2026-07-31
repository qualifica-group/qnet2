import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ContractsTable } from '@/features/contracts/contracts-table'

/**
 * Contracts page (spec 0072, AC-051). Light composition only: gates access
 * with `contracts.viewAny` and mounts the thin Contracts adapter, which in
 * turn mounts the generic table (`domain="contracts"`). The generic table
 * owns config loading and loading/error/empty states; no business logic or
 * data fetching lives here.
 */
export default function ContractsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="contracts.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('contracts.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ContractsTable />
      </div>
    </Can>
  )
}
