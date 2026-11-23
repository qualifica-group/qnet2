import { ArrowRightLeft } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { formatDate } from '@/lib/formatting/date-display'
import type { QuoteRegistryTransfer } from '@/features/quotes/types'

/**
 * Spec 0404 B-D5: the permanent "Trasferita da A a B il gg/mm/aaaa da Nome"
 * line of the offer detail (latest transfer; the full history is in the
 * activity log).
 */
export function QuoteRegistryTransferNotice({ transfer }: { transfer: QuoteRegistryTransfer }) {
  const { t } = useTranslation()

  return (
    <p role="status" className="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs text-muted-foreground">
      <ArrowRightLeft aria-hidden="true" className="size-3.5 shrink-0" />
      <span>
        {t('quotes.registryTransfer.notice', {
          from: transfer.from_registry_name ?? '—',
          to: transfer.to_registry_name ?? '—',
          date: transfer.transferred_at ? formatDate(transfer.transferred_at) : '—',
          causer: transfer.causer_name ?? t('activityLog.systemCauser'),
        })}
      </span>
    </p>
  )
}
