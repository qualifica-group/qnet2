import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { InvoicesTable } from '@/features/invoices/invoices-table'

/**
 * Fatture Attive page (Accounting > Receivables). Light composition only:
 * gates access with `invoices.view` (the menu permission) and mounts the thin
 * adapter over the generic table (`domain="invoices"`).
 */
export default function InvoicesPage() {
  const { t } = useTranslation()

  return (
    <Can permission="invoices.view" fallback={<p className="text-sm text-muted-foreground">{t('invoices.forbidden')}</p>}>
      <div className="flex flex-1 flex-col gap-6">
        <InvoicesTable />
      </div>
    </Can>
  )
}
