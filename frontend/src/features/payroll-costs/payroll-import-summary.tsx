import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import { formatPeriod } from '@/features/payroll-costs/format-period'
import type { PayrollImportResult } from '@/features/payroll-costs/types'

/** Preview of what the file would write (month, counts, total), the replacement warning and the blocking row errors. */
export function PayrollImportSummary({ result }: { result: PayrollImportResult }) {
  const { t, i18n } = useTranslation()
  const { summary, errors, period } = result

  const figures: { label: string; value: string }[] = [
    { label: t('payrollCosts.import.summary.period'), value: period ? formatPeriod(period, i18n.language) : '-' },
    { label: t('payrollCosts.import.summary.rows'), value: String(summary.rows) },
    { label: t('payrollCosts.import.summary.people'), value: String(summary.people) },
    { label: t('payrollCosts.import.summary.companies'), value: String(summary.companies) },
    { label: t('payrollCosts.import.summary.totalCost'), value: formatEuro(summary.total_cost) },
  ]

  return (
    <div className="flex min-h-0 flex-col gap-3">
      <dl className="grid grid-cols-1 gap-x-3 gap-y-0.5 text-xs sm:grid-cols-2">
        {figures.map(({ label, value }) => (
          <div key={label} className="flex justify-between gap-2 border-b py-1">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
          </div>
        ))}
      </dl>
      {summary.replaced_rows > 0 ? (
        <p className="rounded-md border bg-surface px-2 py-1.5 text-xs" role="status">
          {t('payrollCosts.import.replaceWarning', { count: summary.replaced_rows })}
        </p>
      ) : null}
      {errors.length > 0 ? (
        <div className="flex min-h-0 flex-col gap-1">
          <p className="text-xs font-medium text-destructive" role="alert">
            {t('payrollCosts.import.errorsTitle', { count: errors.length })}
          </p>
          <div className="max-h-56 overflow-auto rounded-md border bg-card">
            <table className="w-full text-xs">
              <thead className="sticky top-0 bg-surface text-left">
                <tr>
                  <th scope="col" className="px-2 py-1 font-medium">
                    {t('payrollCosts.import.errors.row')}
                  </th>
                  <th scope="col" className="px-2 py-1 font-medium">
                    {t('payrollCosts.import.errors.column')}
                  </th>
                  <th scope="col" className="px-2 py-1 font-medium">
                    {t('payrollCosts.import.errors.message')}
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
