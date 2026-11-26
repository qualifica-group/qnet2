import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import { formatPeriod } from '@/features/payroll-costs/format-period'
import { PayslipImportIssues } from '@/features/purchase-invoices/payslips/payslip-import-issues'
import type { PayslipImportResult } from '@/features/purchase-invoices/payslips/types'

/** Preview of what the file would write (month, counts, net total, suppliers), then errors and warnings. */
export function PayslipImportSummary({ result }: { result: PayslipImportResult }) {
  const { t, i18n } = useTranslation()
  const { summary, errors, warnings, period } = result
  const label = (key: string) => t(`purchaseInvoiceViews.payslipImport.summary.${key}`)

  const figures: { label: string; value: string }[] = [
    { label: label('period'), value: period ? formatPeriod(period, i18n.language) : '-' },
    { label: label('rows'), value: String(summary.rows) },
    { label: label('people'), value: String(summary.people) },
    { label: label('companies'), value: String(summary.companies) },
    { label: label('totalNet'), value: formatEuro(summary.total_net) },
    { label: label('created'), value: String(summary.created) },
    { label: label('updated'), value: String(summary.updated) },
    { label: label('suppliersCreated'), value: String(summary.suppliers_created) },
    { label: label('suppliersLinked'), value: String(summary.suppliers_linked) },
  ]

  return (
    <div className="flex min-h-0 flex-col gap-3">
      <dl className="grid grid-cols-1 gap-x-3 gap-y-0.5 text-xs sm:grid-cols-2">
        {figures.map(({ label: name, value }) => (
          <div key={name} className="flex justify-between gap-2 border-b py-1">
            <dt className="text-muted-foreground">{name}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
          </div>
        ))}
      </dl>
      {errors.length > 0 ? <PayslipImportIssues issues={errors} variant="error" /> : null}
      {warnings.length > 0 ? <PayslipImportIssues issues={warnings} variant="warning" /> : null}
    </div>
  )
}
