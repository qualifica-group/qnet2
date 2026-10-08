import { useCallback, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { getInstallment, installmentKeys } from '@/features/invoice-installments/api'
import { getInvoice, invoiceKeys } from '@/features/invoices/api'
import { useInvoiceCollectionActions } from '@/features/invoices/use-invoice-collection-actions'
import type { InvoiceInstallment } from '@/features/invoices/types'
import type { RowActionHandler } from '@/features/table/row-actions'

const NO_INSTALLMENTS: readonly InvoiceInstallment[] = []

/**
 * Which dialog/sheet each row action opens. Collection actions reuse the invoice
 * module's dialogs unchanged: they need the whole document's installments (to
 * decide whether a partial residual can be spread), so the document is fetched
 * on demand from the installment's own invoice reference.
 */
export function useInstallmentRowActions(onChanged: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [viewInvoiceId, setViewInvoiceId] = useState<number | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [documentInstallments, setDocumentInstallments] = useState<readonly InvoiceInstallment[]>(NO_INSTALLMENTS)
  const collection = useInvoiceCollectionActions(onChanged)
  const { openCollect, requestClear } = collection

  // Fresh read every time: a collection may have landed since the grid page loaded.
  const fetchInvoice = useCallback(
    (invoiceId: number) =>
      queryClient.fetchQuery({
        queryKey: invoiceKeys.detail(invoiceId),
        queryFn: () => getInvoice(invoiceId),
        staleTime: 0,
      }),
    [queryClient],
  )

  const resolveInstallment = useCallback(
    async (installmentId: number, knownInvoiceId?: number) => {
      if (knownInvoiceId !== undefined) {
        const invoice = await fetchInvoice(knownInvoiceId)
        return { invoiceId: invoice.id, installments: invoice.installments }
      }
      const detail = await queryClient.fetchQuery({
        queryKey: installmentKeys.detail(installmentId),
        queryFn: () => getInstallment(installmentId),
        staleTime: 0,
      })
      const invoice = await fetchInvoice(detail.invoice.id)
      return { invoiceId: invoice.id, installments: invoice.installments }
    },
    [fetchInvoice, queryClient],
  )

  const openViaDocument = useCallback(
    async (
      installmentId: number,
      knownInvoiceId: number | undefined,
      open: (installment: InvoiceInstallment, all: InvoiceInstallment[]) => void,
    ) => {
      try {
        const { installments } = await resolveInstallment(installmentId, knownInvoiceId)
        const installment = installments.find((candidate) => candidate.id === installmentId)
        if (installment) {
          open(installment, installments)
        }
      } catch {
        toast.error(t('invoiceInstallments.lookupError'))
      }
    },
    [resolveInstallment, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action, row) => {
      const id = Number(row.id)
      const invoiceId = typeof row.invoice_id === 'number' ? row.invoice_id : undefined
      switch (action.key) {
        case 'view_invoice':
          void resolveInstallment(id, invoiceId).then(
            (resolved) => setViewInvoiceId(resolved.invoiceId),
            () => toast.error(t('invoiceInstallments.lookupError')),
          )
          break
        case 'edit':
          setEditId(id)
          break
        case 'record_collection':
          void openViaDocument(id, invoiceId, (installment, all) => {
            setDocumentInstallments(all)
            openCollect(installment)
          })
          break
        case 'clear_collection':
          void openViaDocument(id, invoiceId, (installment) => requestClear(installment))
          break
        default:
          break
      }
    },
    [openCollect, openViaDocument, requestClear, resolveInstallment, t],
  )

  return {
    handleAction,
    collection,
    documentInstallments,
    viewInvoiceId,
    closeView: useCallback(() => setViewInvoiceId(null), []),
    editId,
    closeEdit: useCallback(() => setEditId(null), []),
  }
}
