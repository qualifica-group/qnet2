import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { DocumentLayoutsTable } from '@/features/document-layouts/document-layouts-table'

/**
 * Document layouts page. Light composition only: gates access with
 * `document-layouts.viewAny` and mounts the thin Document Layouts adapter,
 * which in turn mounts the generic table (`domain="document-layouts"`). The
 * generic table owns config loading and loading/empty/error states; no
 * business logic or data fetching lives here.
 */
export default function DocumentLayoutsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="document-layouts.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('documentLayouts.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <DocumentLayoutsTable />
      </div>
    </Can>
  )
}
