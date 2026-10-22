import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CompanyClosuresTable } from '@/features/company-closures/company-closures-table'

/**
 * Company closures catalogue page (spec 0304). Light composition only: gates access with
 * `company-closures.view` and mounts the thin adapter over the generic table.
 */
export default function CompanyClosuresPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="company-closures.view"
      fallback={<p className="text-sm text-muted-foreground">{t('companyClosures.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <CompanyClosuresTable />
      </div>
    </Can>
  )
}
