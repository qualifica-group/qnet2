import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { EnasarcoParametersTable } from '@/features/enasarco-parameters/enasarco-parameters-table'

/**
 * ENASARCO parameters page (Accounting > Agent commissions). Light
 * composition only: gates access with `enasarco-parameters.viewAny` (what the
 * table endpoint enforces) and mounts the thin adapter over the generic table.
 */
export default function EnasarcoParametersPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="enasarco-parameters.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('enasarcoParameters.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <EnasarcoParametersTable />
      </div>
    </Can>
  )
}
