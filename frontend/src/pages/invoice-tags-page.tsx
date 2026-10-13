import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { InvoiceTagsTable } from '@/features/invoice-tags/invoice-tags-table'

/**
 * Invoice tags page (spec 0247). Light composition only: gates access with
 * `invoice-tags.viewAny` and mounts the thin adapter, which in turn mounts the
 * generic table (`domain="invoice-tags"`). The generic table owns config loading
 * and loading/error/empty states; no business logic or data fetching lives here.
 */
export function InvoiceTagsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="invoice-tags.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('invoiceTags.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <InvoiceTagsTable />
      </div>
    </Can>
  )
}

export default InvoiceTagsPage
