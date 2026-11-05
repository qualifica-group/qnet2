import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import { defaultStatementFilters } from '@/features/account-statements/api'
import { ReconciliationHistory } from '@/features/account-statements/reconciliation-history'
import { StatementFiltersBar } from '@/features/account-statements/statement-filters-bar'
import { StatementSelectionBar } from '@/features/account-statements/statement-selection-bar'
import { StatementSummary } from '@/features/account-statements/statement-summary'
import { StatementTable } from '@/features/account-statements/statement-table'
import type { ReconciledFilter, StatementRow } from '@/features/account-statements/types'
import { useStatement, useStatementExport } from '@/features/account-statements/use-account-statements'
import { useReconciliationMarks } from '@/features/account-statements/use-reconciliation'

const UNPROCESSABLE = 422

interface AccountStatementPanelProps {
  accountId: number
  /** Initial value of the reconciled filter (from the page URL). */
  initialReconciled?: ReconciledFilter
}

/** Filters, summary and rows of one account's statement; remounted per account via `key`. */
export function AccountStatementPanel({ accountId, initialReconciled = 'all' }: AccountStatementPanelProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [filters, setFilters] = useState(() => defaultStatementFilters(initialReconciled))
  const [selected, setSelected] = useState<ReadonlySet<string>>(new Set())
  const { data, isLoading, error } = useStatement(accountId, filters)
  const exportFile = useStatementExport(accountId, filters)
  const marks = useReconciliationMarks(accountId)
  const canReconcile = can('treasury-movements.reconcile')

  const toggleRow = (key: string) =>
    setSelected((current) => {
      const next = new Set(current)
      if (!next.delete(key)) {
        next.add(key)
      }
      return next
    })
  const toggleAll = (rows: StatementRow[], checked: boolean) =>
    setSelected((current) => {
      const next = new Set(current)
      rows.forEach((row) => (checked ? next.add(row.key) : next.delete(row.key)))
      return next
    })
  const mark = (reconciled: boolean) =>
    marks.mutate({ keys: [...selected], reconciled }, { onSuccess: () => setSelected(new Set()) })

  return (
    <div className="flex flex-col gap-3">
      <StatementFiltersBar filters={filters} onChange={setFilters} />
      <Can permission="treasury-movements.export">
        <div className="flex justify-end">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={exportFile.isPending}
            onClick={() => exportFile.mutate()}
          >
            <Download className="size-3.5" />
            {t('accountStatements.export')}
          </Button>
        </div>
      </Can>
      {error ? (
        <p role="alert" className="rounded-lg border bg-surface p-3 text-sm text-destructive">
          {error.response?.status === UNPROCESSABLE
            ? t('accountStatements.tooManyRows')
            : t('accountStatements.loadError')}
        </p>
      ) : null}
      {isLoading ? <p className="text-sm text-muted-foreground">{t('accountStatements.loading')}</p> : null}
      {data ? (
        <>
          <StatementSummary statement={data} />
          {data.filtered ? (
            <p role="status" className="rounded-lg border bg-surface p-2 text-xs text-muted-foreground">
              {t('accountStatements.filteredNotice')}
            </p>
          ) : null}
          {canReconcile ? (
            <StatementSelectionBar count={selected.size} isPending={marks.isPending} onMark={mark} />
          ) : null}
          <StatementTable
            rows={data.rows}
            selection={
              canReconcile
                ? { selected, onToggle: toggleRow, onToggleAll: (checked) => toggleAll(data.rows, checked) }
                : undefined
            }
          />
        </>
      ) : null}
      <ReconciliationHistory accountId={accountId} />
    </div>
  )
}
