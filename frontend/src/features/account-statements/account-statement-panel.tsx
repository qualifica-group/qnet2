import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { defaultStatementFilters } from '@/features/account-statements/api'
import { StatementFiltersBar } from '@/features/account-statements/statement-filters-bar'
import { StatementSummary } from '@/features/account-statements/statement-summary'
import { StatementTable } from '@/features/account-statements/statement-table'
import { useStatement, useStatementExport } from '@/features/account-statements/use-account-statements'

const UNPROCESSABLE = 422

/** Filters, summary and rows of one account's statement; remounted per account via `key`. */
export function AccountStatementPanel({ accountId }: { accountId: number }) {
  const { t } = useTranslation()
  const [filters, setFilters] = useState(defaultStatementFilters)
  const { data, isLoading, error } = useStatement(accountId, filters)
  const exportFile = useStatementExport(accountId, filters)

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
          <StatementTable rows={data.rows} />
        </>
      ) : null}
    </div>
  )
}
