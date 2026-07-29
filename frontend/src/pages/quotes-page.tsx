import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { QuotesTable } from '@/features/quotes/quotes-table'

/**
 * Quotes page (spec 0065, AC-078). Light composition only: gates access with
 * `quotes.viewAny` and mounts the thin Quotes adapter, which in turn mounts
 * the generic table (`domain="quotes"`). The generic table owns config
 * loading and loading/error/empty states; no business logic or data fetching
 * lives here.
 */
export default function QuotesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="quotes.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('quotes.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <QuotesTable />
      </div>
    </Can>
  )
}
