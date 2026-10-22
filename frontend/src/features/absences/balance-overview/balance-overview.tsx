import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronLeft, ChevronRight, Download, SlidersHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { BalanceOverviewFilters } from '@/features/absences/balance-overview/balance-overview-filters'
import { BalanceOverviewTable } from '@/features/absences/balance-overview/balance-overview-table'
import { BulkAllowanceDialog } from '@/features/absences/balance-overview/bulk-allowance-dialog'
import { useBalanceExport } from '@/features/absences/balance-overview/use-balance-export'
import { useBalanceOverview } from '@/features/absences/balance-overview/use-balance-overview'
import { useBalanceOverviewFilters } from '@/features/absences/balance-overview/use-balance-overview-filters'
import { usePersonSelection } from '@/features/absences/balance-overview/use-person-selection'
import { BALANCE_EXPORT_FORMATS } from '@/features/absences/types'

/**
 * "Saldi" view of the HR office (spec 0304 D-18, `absence-requests.manageAll`): everybody's balances per type
 * with filters, CSV/XLSX export and a bulk "Imposta spettanza" on the selected people.
 */
export function BalanceOverview() {
  const { t } = useTranslation()
  const filters = useBalanceOverviewFilters()
  const query = useBalanceOverview(filters.params)
  const exporter = useBalanceExport(filters.filters)
  const selection = usePersonSelection()
  const [bulkOpen, setBulkOpen] = useState(false)
  const overview = query.data?.data
  const meta = query.data?.meta
  const selectedCount = selection.selected.size

  return (
    <section className="flex flex-col gap-3" aria-label={t('absences.balances.overview.title')}>
      <BalanceOverviewFilters filters={filters} />
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Button type="button" size="sm" disabled={selectedCount === 0} onClick={() => setBulkOpen(true)}>
          <SlidersHorizontal className="size-3.5" aria-hidden="true" />
          {t('absences.balances.overview.bulk.open', { count: selectedCount })}
        </Button>
        <div className="flex items-center gap-2">
          {BALANCE_EXPORT_FORMATS.map((format) => (
            <Button
              key={format}
              type="button"
              size="sm"
              variant="secondary"
              disabled={exporter.isPending}
              onClick={() => exporter.mutate(format)}
            >
              <Download className="size-3.5" aria-hidden="true" />
              {t(`absences.balances.overview.export.${format}`)}
            </Button>
          ))}
        </div>
      </div>

      {query.isPending ? <Skeleton className="h-40 w-full" /> : null}
      {query.isError ? (
        <div className="flex items-center gap-2 text-sm text-destructive" role="alert">
          {t('absences.balances.error')}
          <Button type="button" size="sm" variant="secondary" onClick={() => void query.refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : null}
      {overview && overview.people.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('absences.balances.overview.empty')}</p>
      ) : null}
      {overview && overview.people.length > 0 ? (
        <BalanceOverviewTable
          overview={overview}
          selected={selection.selected}
          onToggle={selection.toggle}
          onTogglePage={selection.setMany}
        />
      ) : null}
      {meta && meta.last_page > 1 ? (
        <nav className="flex items-center justify-between gap-2 text-xs" aria-label={t('absences.balances.overview.pagination.label')}>
          <span className="text-muted-foreground">
            {t('absences.balances.overview.pagination.summary', {
              page: meta.current_page,
              pages: meta.last_page,
              total: meta.total,
            })}
          </span>
          <div className="flex gap-1">
            <Button
              type="button"
              size="sm"
              variant="secondary"
              disabled={meta.current_page <= 1}
              onClick={() => filters.setPage(meta.current_page - 1)}
            >
              <ChevronLeft className="size-3.5" aria-hidden="true" />
              {t('absences.balances.overview.pagination.previous')}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="secondary"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => filters.setPage(meta.current_page + 1)}
            >
              {t('absences.balances.overview.pagination.next')}
              <ChevronRight className="size-3.5" aria-hidden="true" />
            </Button>
          </div>
        </nav>
      ) : null}

      {overview && bulkOpen ? (
        <BulkAllowanceDialog
          onOpenChange={setBulkOpen}
          types={overview.types}
          year={overview.year}
          userIds={[...selection.selected]}
          onDone={() => {
            setBulkOpen(false)
            selection.clear()
          }}
        />
      ) : null}
    </section>
  )
}
