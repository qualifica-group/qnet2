import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { DocumentChecklistCategoriesTable } from '@/features/document-checklist-categories/document-checklist-categories-table'

/** Checklist categories page (spec 0410): gated by the checklists' `viewAny` permission (D-2), mounts the thin table adapter. */
export default function DocumentChecklistCategoriesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="document-checklists.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('documentChecklistCategories.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <DocumentChecklistCategoriesTable />
      </div>
    </Can>
  )
}
