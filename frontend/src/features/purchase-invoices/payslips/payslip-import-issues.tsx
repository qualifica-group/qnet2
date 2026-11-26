import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import type { PayslipImportIssue } from '@/features/purchase-invoices/payslips/types'

interface PayslipImportIssuesProps {
  issues: PayslipImportIssue[]
  variant: 'error' | 'warning'
}

const BASE_KEY = 'purchaseInvoiceViews.payslipImport'

/** Row-by-row table of blocking errors (destructive) or non-blocking warnings (neutral, never red). */
export function PayslipImportIssues({ issues, variant }: PayslipImportIssuesProps) {
  const { t } = useTranslation()
  const isError = variant === 'error'

  return (
    <div className="flex min-h-0 flex-col gap-1">
      <p className={cn('text-xs font-medium', isError ? 'text-destructive' : 'text-foreground')} role={isError ? 'alert' : 'status'}>
        {t(isError ? `${BASE_KEY}.errorsTitle` : `${BASE_KEY}.warningsTitle`, { count: issues.length })}
      </p>
      <div className={cn('max-h-40 overflow-auto rounded-md border', isError ? 'border-destructive/40 bg-card' : 'bg-surface')}>
        <table className="w-full text-xs">
          <thead className="sticky top-0 bg-surface text-left">
            <tr>
              <th scope="col" className="px-2 py-1 font-medium">{t(`${BASE_KEY}.issues.row`)}</th>
              <th scope="col" className="px-2 py-1 font-medium">{t(`${BASE_KEY}.issues.column`)}</th>
              <th scope="col" className="px-2 py-1 font-medium">{t(`${BASE_KEY}.issues.message`)}</th>
            </tr>
          </thead>
          <tbody>
            {issues.map((issue) => (
              <tr key={`${issue.row}-${issue.column ?? ''}-${issue.message}`} className="border-t align-top">
                <td className="px-2 py-1 tabular-nums">{issue.row}</td>
                <td className="px-2 py-1">{issue.column ?? '-'}</td>
                <td className="px-2 py-1">{issue.message}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
