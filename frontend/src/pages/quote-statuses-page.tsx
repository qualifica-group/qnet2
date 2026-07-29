import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { QuoteStatusesTable } from '@/features/quote-statuses/quote-statuses-table'

/**
 * Quote statuses page. Light composition only: gates access with
 * `quote-statuses.viewAny` and mounts the thin Quote Statuses adapter, which
 * in turn mounts the generic table (`domain="quote-statuses"`). The generic
 * table owns config loading and loading/error/empty states; no business
 * logic or data fetching lives here.
 */
export default function QuoteStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="quote-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('quoteStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <QuoteStatusesTable />
      </div>
    </Can>
  )
}
