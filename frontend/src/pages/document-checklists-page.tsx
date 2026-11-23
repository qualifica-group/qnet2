import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { DocumentChecklistsTable } from '@/features/document-checklists/document-checklists-table'

/** Document checklists page (spec 0403): gates access with `document-checklists.viewAny` and mounts the thin table adapter. */
export default function DocumentChecklistsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="document-checklists.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('documentChecklists.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <DocumentChecklistsTable />
      </div>
    </Can>
  )
}
