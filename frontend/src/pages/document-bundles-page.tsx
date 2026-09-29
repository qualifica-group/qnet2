import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { DocumentBundlesTable } from '@/features/document-bundles/document-bundles-table'

/**
 * Document bundles page (spec 0175). Light composition only: gates access
 * with `document-bundles.viewAny` and mounts the thin adapter, which in turn
 * mounts the generic table (`domain="document-bundles"`). The generic table
 * owns config loading and loading/error/empty states; no business logic or
 * data fetching lives here (mirrors `TaskImportancesPage`).
 */
export default function DocumentBundlesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="document-bundles.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('documentBundles.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <DocumentBundlesTable />
      </div>
    </Can>
  )
}
