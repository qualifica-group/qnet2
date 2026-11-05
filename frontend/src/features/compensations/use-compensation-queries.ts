import { useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { accountStatementKeys } from '@/features/account-statements/api'
import {
  compensationKeys,
  createCompensation,
  deleteCompensation,
  fetchCandidates,
  fetchCompensations,
} from '@/features/compensations/api'
import type {
  CompensationCandidates,
  CompensationPayload,
  InstallmentCompensation,
} from '@/features/compensations/types'
import { INVOICE_INSTALLMENTS_DOMAIN } from '@/features/invoice-installments/api'
import { invoiceKeys } from '@/features/invoices/api'
import { ledgerKeys } from '@/features/ledger/api'
import { purchaseInstallmentKeys } from '@/features/purchase-invoice-installments/api'
import { purchaseInvoiceKeys } from '@/features/purchase-invoices/api'
import { treasuryOverviewKeys } from '@/features/treasury-overview/api'

/** A compensation (or its undoing) moves both installments and their documents: every surface showing them reloads. */
function invalidateCompensationSurfaces(queryClient: QueryClient): void {
  const roots = [
    compensationKeys.all,
    ledgerKeys.all,
    [INVOICE_INSTALLMENTS_DOMAIN],
    purchaseInstallmentKeys.all,
    invoiceKeys.all,
    purchaseInvoiceKeys.all,
    accountStatementKeys.all,
    treasuryOverviewKeys.all,
  ]
  roots.forEach((queryKey) => void queryClient.invalidateQueries({ queryKey }))
}

/** Open installments of the registry in the company; disabled until a company is chosen. */
export function useCompensationCandidates(registryId: number, companyId: number | null) {
  return useQuery<CompensationCandidates, AxiosError>({
    queryKey: compensationKeys.candidates(registryId, companyId ?? 0),
    queryFn: () => fetchCandidates(registryId, companyId as number),
    enabled: companyId !== null,
    gcTime: 0,
  })
}

export function useRegistryCompensations(registryId: number, enabled: boolean) {
  return useQuery<InstallmentCompensation[], AxiosError>({
    queryKey: compensationKeys.list(registryId),
    queryFn: () => fetchCompensations(registryId),
    enabled,
  })
}

export function useCreateCompensation() {
  const queryClient = useQueryClient()
  return useMutation<InstallmentCompensation, AxiosError, CompensationPayload>({
    mutationFn: createCompensation,
    onSuccess: () => invalidateCompensationSurfaces(queryClient),
  })
}

export function useDeleteCompensation() {
  const queryClient = useQueryClient()
  return useMutation<void, AxiosError, number>({
    mutationFn: deleteCompensation,
    onSuccess: () => invalidateCompensationSurfaces(queryClient),
  })
}
