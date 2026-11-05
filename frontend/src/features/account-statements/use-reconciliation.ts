import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { toast } from 'sonner'
import { useTranslation } from 'react-i18next'
import type { i18n as I18nInstance } from 'i18next'
import { forbiddenMessage } from '@/lib/forbidden-message'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import {
  accountStatementKeys,
  createReconciliation,
  deleteReconciliation,
  fetchReconciliationPreview,
  fetchReconciliations,
  setReconciliationMarks,
} from '@/features/account-statements/api'
import type {
  BankReconciliation,
  ReconciliationPayload,
  ReconciliationPreview,
} from '@/features/account-statements/types'
import { treasuryOverviewKeys } from '@/features/treasury-overview/api'

/** 403 sentence, else the server's own reason (e.g. a 422 on a foreign row), else the fallback. */
function errorMessage(error: unknown, fallback: string, i18n: I18nInstance): string {
  return forbiddenMessage(error, i18n) ?? serverMessage(error) ?? fallback
}

/** Statement, balances, reconciliations and the overview all derive from the ticked rows. */
function useInvalidateReconciliationData() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: accountStatementKeys.all }),
      queryClient.invalidateQueries({ queryKey: treasuryOverviewKeys.all }),
    ])
}

/** Ticks/unticks the selected statement rows (spec 0363). */
export function useReconciliationMarks(accountId: number) {
  const { t, i18n } = useTranslation()
  const invalidate = useInvalidateReconciliationData()
  return useMutation<number, AxiosError, { keys: string[]; reconciled: boolean }>({
    mutationFn: ({ keys, reconciled }) => setReconciliationMarks(accountId, keys, reconciled),
    onSuccess: async () => {
      await invalidate()
    },
    onError: (error) => toast.error(errorMessage(error, t('accountStatements.reconciliation.marksError'), i18n)),
  })
}

export function useReconciliations(accountId: number) {
  return useQuery<BankReconciliation[], AxiosError>({
    queryKey: accountStatementKeys.reconciliations(accountId),
    queryFn: () => fetchReconciliations(accountId),
  })
}

/** Ticked balance at a date; only runs once a date is entered. */
export function useReconciliationPreview(accountId: number, date: string) {
  return useQuery<ReconciliationPreview, AxiosError>({
    queryKey: accountStatementKeys.preview(accountId, date),
    queryFn: () => fetchReconciliationPreview(accountId, date),
    enabled: date !== '',
    retry: false,
  })
}

export function useCreateReconciliation(accountId: number) {
  const invalidate = useInvalidateReconciliationData()
  return useMutation<BankReconciliation, AxiosError, ReconciliationPayload>({
    mutationFn: (payload) => createReconciliation(accountId, payload),
    onSuccess: async () => {
      await invalidate()
    },
  })
}

export function useDeleteReconciliation() {
  const { t, i18n } = useTranslation()
  const invalidate = useInvalidateReconciliationData()
  return useMutation<void, AxiosError, number>({
    mutationFn: deleteReconciliation,
    onSuccess: async () => {
      toast.success(t('accountStatements.reconciliation.deleted'))
      await invalidate()
    },
    onError: (error) => toast.error(errorMessage(error, t('accountStatements.reconciliation.deleteError'), i18n)),
  })
}
