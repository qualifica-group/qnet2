import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { defaultLedgerFilters } from '@/features/ledger/api'
import { LedgerFiltersBar } from '@/features/ledger/ledger-filters-bar'
import { LedgerSection } from '@/features/ledger/ledger-section'
import { useLedger } from '@/features/ledger/use-ledger'
import type { LedgerFilters } from '@/features/ledger/types'

interface LedgerPanelProps {
  registryId: number
}

/**
 * Ledger of one registry (spec 0266): filters, then one section per side the
 * server returned (Cliente above Fornitore). The registry is chosen by the
 * caller (page selector or record tab), so no selector lives here.
 */
export function LedgerPanel({ registryId }: LedgerPanelProps) {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<LedgerFilters>(defaultLedgerFilters)
  const { data, isPending, isError, refetch } = useLedger(registryId, filters)

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <LedgerFiltersBar filters={filters} onChange={setFilters} />
      {isPending ? (
        <div role="status" aria-label={t('ledger.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ) : null}
      {isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          <span>{t('ledger.error')}</span>
          <Button type="button" size="sm" variant="secondary" onClick={() => void refetch()}>
            {t('ledger.retry')}
          </Button>
        </div>
      ) : null}
      {data?.customer ? (
        <LedgerSection side="customer" section={data.customer} showOpeningBalance={filters.dateFrom !== ''} />
      ) : null}
      {data?.supplier ? (
        <LedgerSection side="supplier" section={data.supplier} showOpeningBalance={filters.dateFrom !== ''} />
      ) : null}
    </div>
  )
}
