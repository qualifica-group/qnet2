import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
// Side effect: registers the `importWizard` i18next namespace before this
// page's own `t()` calls run (see `features/imports/wizard/i18n.ts`).
import '@/features/imports/wizard/i18n'
import { PageHeader } from '@/components/page-header'
import { ImportWizard } from '@/features/imports/wizard/import-wizard'

const REQUEST_IMPORT_DOMAIN = 'request-management'

/**
 * Request-management import wizard page (spec 0348): the same wizard as the
 * lead import, gated on `request-management.import` — the ability the backend
 * enforces on every endpoint of the domain.
 */
export default function RequestImportPage() {
  const { t } = useTranslation('importWizard')

  return (
    <Can
      permission="request-management.import"
      fallback={<p className="text-sm text-muted-foreground">{t(`domains.${REQUEST_IMPORT_DOMAIN}.forbidden`)}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <ImportWizard domain={REQUEST_IMPORT_DOMAIN} />
      </div>
    </Can>
  )
}
