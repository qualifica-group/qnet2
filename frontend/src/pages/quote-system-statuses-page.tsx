import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { QuoteSystemStatusesTable } from '@/features/quote-system-statuses/quote-system-statuses-table'

/**
 * Quote system statuses page (spec 0404). Light composition only: gates access
 * with `quote-system-statuses.viewAny` and mounts the thin Quote System Statuses
 * adapter, which in turn mounts the generic table (`domain="quote-system-statuses"`).
 * The generic table owns config loading and loading/error/empty states; no
 * business logic or data fetching lives here.
 */
export default function QuoteSystemStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="quote-system-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('quoteSystemStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <QuoteSystemStatusesTable />
      </div>
    </Can>
  )
}
