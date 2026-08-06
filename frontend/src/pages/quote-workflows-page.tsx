import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { QuoteWorkflowsTable } from '@/features/quote-workflows/quote-workflows-table'

/**
 * Quote workflow configurator page (spec 0047 Lane C, moved onto the Offerta
 * by spec 0083 D-6). Light composition only: gates access with
 * `quote-workflows.viewAny` and mounts the thin Quote Workflows adapter,
 * which in turn mounts the generic table (`domain="quote-workflows"`). The
 * generic table owns config loading and loading/error/empty states; no
 * business logic or data fetching lives here.
 */
export default function QuoteWorkflowsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="quote-workflows.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('quoteWorkflows.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <QuoteWorkflowsTable />
      </div>
    </Can>
  )
}
