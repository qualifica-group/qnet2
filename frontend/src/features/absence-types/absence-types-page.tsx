import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { AbsenceTypesTable } from '@/features/absence-types/absence-types-table'

/**
 * Absence types catalogue page (spec 0278). Light composition only: gates access with
 * `absence-types.view` and mounts the thin adapter over the generic table.
 */
export default function AbsenceTypesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="absence-types.view"
      fallback={<p className="text-sm text-muted-foreground">{t('absenceTypes.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <AbsenceTypesTable />
      </div>
    </Can>
  )
}
