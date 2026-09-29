import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { EmailTemplatesTable } from '@/features/email-templates/email-templates-table'

/**
 * Email templates page (spec 0175). Light composition only: gates access
 * with `email-templates.viewAny` and mounts the thin adapter, which in turn
 * mounts the generic table (`domain="email-templates"`). The generic table
 * owns config loading and loading/error/empty states; no business logic or
 * data fetching lives here (mirrors `TaskImportancesPage`).
 */
export default function EmailTemplatesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="email-templates.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('emailTemplates.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <EmailTemplatesTable />
      </div>
    </Can>
  )
}
