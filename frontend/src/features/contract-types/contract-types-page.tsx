import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ContractTypesTable } from '@/features/contract-types/contract-types-table'

/**
 * Contract types catalogue page (spec 0290). Light composition only: gates access with
 * `contract-types.view` and mounts the thin adapter over the generic table.
 */
export default function ContractTypesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="contract-types.view"
      fallback={<p className="text-sm text-muted-foreground">{t('contractTypes.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ContractTypesTable />
      </div>
    </Can>
  )
}
