import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ProformaRequestsTable } from '@/features/proforma-requests/proforma-requests-table'

/**
 * Proforma requests page (Accounting > Receivables). Light composition only:
 * gates access with `proforma-requests.viewAny` and mounts the thin adapter
 * over the generic table (`domain="proforma-requests"`).
 */
export default function ProformaRequestsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="proforma-requests.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('proformaRequests.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ProformaRequestsTable />
      </div>
    </Can>
  )
}
