import { useTranslation } from 'react-i18next'
import { InvoiceClearCollectionDialog } from '@/features/invoices/invoice-clear-collection-dialog'
import { InvoiceCollectionDialog } from '@/features/invoices/invoice-collection-dialog'
import { InvoiceInstallmentsTable } from '@/features/invoices/invoice-installments-table'
import { useInvoiceCollectionActions } from '@/features/invoices/use-invoice-collection-actions'
import type { InvoiceWithPermissions } from '@/features/invoices/types'

interface InvoiceInstallmentsSectionProps {
  invoice: InvoiceWithPermissions
  /** Called after a collection is recorded/cleared (the caller refreshes its grid). */
  onChanged?: () => void
}

/** "Scadenze" of a document with the collection actions, gated by the show permissions. */
export function InvoiceInstallmentsSection({ invoice, onChanged }: InvoiceInstallmentsSectionProps) {
  const { t } = useTranslation()
  const actions = useInvoiceCollectionActions(onChanged)

  return (
    <section className="flex flex-col gap-1.5">
      <h3 className="text-xs font-semibold text-foreground">{t('invoices.detail.installments')}</h3>
      <InvoiceInstallmentsTable
        installments={invoice.installments}
        canCollect={invoice.permissions.actions.collect === true}
        busyInstallmentId={actions.busyInstallmentId}
        onRecord={actions.openCollect}
        onClear={actions.requestClear}
      />
      <InvoiceCollectionDialog
        installment={actions.collectTarget}
        installments={invoice.installments}
        onClose={actions.closeCollect}
        onSaved={onChanged}
      />
      <InvoiceClearCollectionDialog
        target={actions.clearTarget}
        isPending={actions.isClearing}
        onClose={actions.closeClear}
        onConfirm={actions.confirmClear}
      />
    </section>
  )
}
