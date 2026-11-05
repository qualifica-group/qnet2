import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { useConfirm } from '@/components/confirm-dialog-context'
import { deleteChartAccount, deleteRevenueType } from '@/features/chart-of-accounts/api'
import { chartOfAccountsKeys } from '@/features/chart-of-accounts/query-keys'
import type { TreeRow } from '@/features/chart-of-accounts/tree-model'
import { deleteCostType } from '@/features/cost-types/api'
import { apiErrorMessage } from '@/lib/forbidden-message'

const HTTP_CONFLICT = 409

function deleteRequest(row: TreeRow): (() => Promise<void>) | null {
  switch (row.type) {
    case 'group':
    case 'account':
      return () => deleteChartAccount(row.node.id)
    case 'sub':
      return row.node.source === 'revenue_type' ? () => deleteRevenueType(row.node.id) : () => deleteCostType(row.node.id)
    case 'unplaced':
      return null
  }
}

/** Confirm, delete, refresh the tree. A 409 (children / in use) surfaces the backend message as a toast. */
export function useDeleteEntry() {
  const { t, i18n } = useTranslation()
  const confirm = useConfirm()
  const queryClient = useQueryClient()

  const mutation = useMutation({
    mutationFn: (request: () => Promise<void>) => request(),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chartOfAccountsKeys.tree() })
      toast.success(t('chartOfAccounts.deleted'))
    },
    onError: (error) => {
      if (axios.isAxiosError<ApiErrorResponse>(error) && error.response?.status === HTTP_CONFLICT) {
        toast.error(error.response.data?.message ?? t('chartOfAccounts.deleteConflict'))
        return
      }
      toast.error(apiErrorMessage(error, t('chartOfAccounts.deleteError'), i18n))
    },
  })

  return async (row: TreeRow, label: string) => {
    const request = deleteRequest(row)
    if (!request) return
    const confirmed = await confirm({
      title: t('chartOfAccounts.deleteConfirm.title'),
      description: t('chartOfAccounts.deleteConfirm.description', { label }),
      confirmLabel: t('chartOfAccounts.actions.delete'),
      tone: 'destructive',
    })
    if (confirmed) mutation.mutate(request)
  }
}
