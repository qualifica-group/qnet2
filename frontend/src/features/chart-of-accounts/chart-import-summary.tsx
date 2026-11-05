import { useTranslation } from 'react-i18next'
import type { ChartImportCounts, ChartImportResult } from '@/features/chart-of-accounts/types'

const COUNT_ROWS: { key: keyof ChartImportCounts; labelKey: string }[] = [
  { key: 'groups', labelKey: 'chartOfAccounts.import.counts.groups' },
  { key: 'accounts', labelKey: 'chartOfAccounts.import.counts.accounts' },
  { key: 'cost_sub_accounts', labelKey: 'chartOfAccounts.import.counts.costSubAccounts' },
  { key: 'revenue_sub_accounts', labelKey: 'chartOfAccounts.import.counts.revenueSubAccounts' },
]

/** Preview of what the file would create / update, and the row errors that block it. */
export function ChartImportSummary({ result }: { result: ChartImportResult }) {
  const { t } = useTranslation()
  const { summary, errors } = result

  return (
    <div className="flex min-h-0 flex-col gap-3">
      <p className="text-xs text-muted-foreground">{t('chartOfAccounts.import.rows', { count: summary.rows })}</p>
      <table className="w-full text-xs">
        <caption className="sr-only">{t('chartOfAccounts.import.countsCaption')}</caption>
        <thead>
          <tr className="border-b text-left text-muted-foreground">
            <th scope="col" className="py-1 pr-2 font-medium">
              {t('chartOfAccounts.import.counts.level')}
            </th>
            <th scope="col" className="px-2 py-1 text-right font-medium">
              {t('chartOfAccounts.import.counts.create')}
            </th>
            <th scope="col" className="py-1 pl-2 text-right font-medium">
              {t('chartOfAccounts.import.counts.update')}
            </th>
          </tr>
        </thead>
        <tbody>
          {COUNT_ROWS.map(({ key, labelKey }) => (
            <tr key={key} className="border-b last:border-0">
              <th scope="row" className="py-1 pr-2 text-left font-normal">
                {t(labelKey)}
              </th>
              <td className="px-2 py-1 text-right tabular-nums">{summary.create[key]}</td>
              <td className="py-1 pl-2 text-right tabular-nums">{summary.update[key]}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {errors.length > 0 ? (
        <div className="flex min-h-0 flex-col gap-1">
          <p className="text-xs font-medium text-destructive" role="alert">
            {t('chartOfAccounts.import.errorsTitle', { count: errors.length })}
          </p>
          <div className="max-h-56 overflow-auto rounded-md border bg-card">
            <table className="w-full text-xs">
              <thead className="sticky top-0 bg-surface text-left">
                <tr>
                  <th scope="col" className="px-2 py-1 font-medium">
                    {t('chartOfAccounts.import.errors.row')}
                  </th>
                  <th scope="col" className="px-2 py-1 font-medium">
                    {t('chartOfAccounts.import.errors.column')}
                  </th>
                  <th scope="col" className="px-2 py-1 font-medium">
                    {t('chartOfAccounts.import.errors.message')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {errors.map((error) => (
                  <tr key={`${error.row}-${error.column ?? ''}-${error.message}`} className="border-t align-top">
                    <td className="px-2 py-1 tabular-nums">{error.row}</td>
                    <td className="px-2 py-1">{error.column ?? '-'}</td>
                    <td className="px-2 py-1">{error.message}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}
    </div>
  )
}
