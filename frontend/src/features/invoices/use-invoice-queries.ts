import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  clearCollection,
  createInvoice,
  deleteInvoice,
  getInvoice,
  getInvoiceDraft,
  getMonthlySummary,
  invoiceKeys,
  previewInstallments,
  recordCollection,
  updateInvoice,
  updateInvoiceDetails,
} from '@/features/invoices/api'
import type {
  Invoice,
  InvoiceCollectionPayload,
  InvoiceDetailsPayload,
  InvoiceDraft,
  InvoiceWithPermissions,
  InvoiceWritePayload,
  InstallmentPreviewPayload,
  InstallmentPreviewRow,
  MonthlySummary,
  MonthlySummaryParams,
} from '@/features/invoices/types'
import { PROFORMA_REQUESTS_DOMAIN } from '@/features/proforma-requests/api'

/** Draft of the issue modal; always refetched on open (the request may have been invoiced meanwhile). */
export function useInvoiceDraft(
  proformaRequestId: number | null,
): UseQueryResult<InvoiceDraft, AxiosError> {
  return useQuery<InvoiceDraft, AxiosError>({
    queryKey: invoiceKeys.draft(proformaRequestId ?? 0),
    queryFn: () => getInvoiceDraft(proformaRequestId as number),
    enabled: proformaRequestId !== null,
    gcTime: 0,
  })
}

/** Installment schedule preview; pass `null` to keep it idle until the inputs are valid. */
export function useInstallmentPreview(
  payload: InstallmentPreviewPayload | null,
): UseQueryResult<InstallmentPreviewRow[], AxiosError> {
  return useQuery<InstallmentPreviewRow[], AxiosError>({
    queryKey: invoiceKeys.preview(
      payload ?? { document_date: '', payment_method_id: 0, net_amount: 0, vat_amount: 0, total_amount: 0 },
    ),
    queryFn: () => previewInstallments(payload as InstallmentPreviewPayload),
    enabled: payload !== null,
  })
}

/** One document with lines, installments and permissions. */
export function useInvoice(id: number | null): UseQueryResult<InvoiceWithPermissions, AxiosError> {
  return useQuery<InvoiceWithPermissions, AxiosError>({
    queryKey: invoiceKeys.detail(id ?? 0),
    queryFn: () => getInvoice(id as number),
    enabled: id !== null,
  })
}

export function useMonthlySummary(
  params: MonthlySummaryParams,
): UseQueryResult<MonthlySummary, AxiosError> {
  return useQuery<MonthlySummary, AxiosError>({
    queryKey: invoiceKeys.monthlySummary(params),
    queryFn: () => getMonthlySummary(params),
  })
}

/** Issues the document; also refreshes the proforma requests grid (the request becomes issued). */
export function useCreateInvoice(
  proformaRequestId: number,
): UseMutationResult<Invoice, AxiosError, InvoiceWritePayload> {
  const queryClient = useQueryClient()
  return useMutation<Invoice, AxiosError, InvoiceWritePayload>({
    mutationFn: (payload) => createInvoice(proformaRequestId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      void queryClient.invalidateQueries({ queryKey: [PROFORMA_REQUESTS_DOMAIN] })
    },
  })
}

export function useUpdateInvoice(
  id: number,
): UseMutationResult<InvoiceWithPermissions, AxiosError, InvoiceWritePayload> {
  const queryClient = useQueryClient()
  return useMutation<InvoiceWithPermissions, AxiosError, InvoiceWritePayload>({
    mutationFn: (payload) => updateInvoice(id, payload),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: invoiceKeys.all }),
  })
}

export function useUpdateInvoiceDetails(
  id: number,
): UseMutationResult<InvoiceWithPermissions, AxiosError, InvoiceDetailsPayload> {
  const queryClient = useQueryClient()
  return useMutation<InvoiceWithPermissions, AxiosError, InvoiceDetailsPayload>({
    mutationFn: (payload) => updateInvoiceDetails(id, payload),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: invoiceKeys.all }),
  })
}

/** Deletes the document; the linked proforma request goes back to pending. */
export function useDeleteInvoice(): UseMutationResult<void, AxiosError, number> {
  const queryClient = useQueryClient()
  return useMutation<void, AxiosError, number>({
    mutationFn: (id) => deleteInvoice(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      void queryClient.invalidateQueries({ queryKey: [PROFORMA_REQUESTS_DOMAIN] })
    },
  })
}

interface RecordCollectionVariables {
  installmentId: number
  body: InvoiceCollectionPayload
}

export function useRecordCollection(): UseMutationResult<
  Invoice,
  AxiosError,
  RecordCollectionVariables
> {
  const queryClient = useQueryClient()
  return useMutation<Invoice, AxiosError, RecordCollectionVariables>({
    mutationFn: ({ installmentId, body }) => recordCollection(installmentId, body),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: invoiceKeys.all }),
  })
}

export function useClearCollection(): UseMutationResult<Invoice, AxiosError, number> {
  const queryClient = useQueryClient()
  return useMutation<Invoice, AxiosError, number>({
    mutationFn: (installmentId) => clearCollection(installmentId),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: invoiceKeys.all }),
  })
}
