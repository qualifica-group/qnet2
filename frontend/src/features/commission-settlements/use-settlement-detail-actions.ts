import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import {
  cancelSettlement,
  commissionSettlementKeys,
  deleteAdjustment,
  deleteSettlementDocument,
  deleteSettlement,
  issueSettlement,
  recalculateSettlement,
  updateSettlementNotes,
} from '@/features/commission-settlements/api'
import { settlementErrorMessage } from '@/features/commission-settlements/settlement-errors'
import type { SettlementDetailWithPermissions } from '@/features/commission-settlements/types'

const CONFIRM_TONES = {
  issue: 'default',
  delete: 'destructive',
  deleteAdjustment: 'destructive',
  removeDocument: 'warning',
} as const

interface UseSettlementDetailActionsArgs {
  settlementId: number
  /** Called after any change so the caller refreshes its grid. */
  onChanged: () => void
  /** Called after the draft was deleted (the record no longer exists). */
  onDeleted: () => void
}

/**
 * Every write of the settlement detail (recalculate, issue, cancel, delete,
 * notes, adjustment removal, document removal; the document and approval dialogs
 * own their forms). Server conflicts (409) and rule violations (422
 * `reason`) surface as a toast with the server message; the cached detail is
 * replaced with the fresh response so the abilities re-enable the right buttons.
 */
export function useSettlementDetailActions({ settlementId, onChanged, onDeleted }: UseSettlementDetailActionsArgs) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const queryClient = useQueryClient()
  const detailKey = commissionSettlementKeys.detail(settlementId)

  const onError = (error: unknown) => {
    toast.error(settlementErrorMessage(error, t) ?? t('commissionSettlements.actions.genericError'))
  }
  const apply = (message: string) => (data: SettlementDetailWithPermissions) => {
    queryClient.setQueryData(detailKey, data)
    toast.success(t(message))
    onChanged()
  }

  const recalculate = useMutation({
    mutationFn: () => recalculateSettlement(settlementId),
    onSuccess: apply('commissionSettlements.actions.recalculated'),
    onError,
  })
  const issue = useMutation({
    mutationFn: () => issueSettlement(settlementId),
    onSuccess: apply('commissionSettlements.actions.issued'),
    onError,
  })
  const cancel = useMutation({
    mutationFn: (reason: string) => cancelSettlement(settlementId, { reason }),
    onSuccess: apply('commissionSettlements.actions.cancelled'),
    onError,
  })
  const saveNotes = useMutation({
    mutationFn: (notes: string | null) => updateSettlementNotes(settlementId, { notes }),
    onSuccess: apply('commissionSettlements.actions.notesSaved'),
    onError,
  })
  const remove = useMutation({
    mutationFn: () => deleteSettlement(settlementId),
    onSuccess: () => {
      queryClient.removeQueries({ queryKey: detailKey })
      toast.success(t('commissionSettlements.actions.deleted'))
      onChanged()
      onDeleted()
    },
    onError,
  })
  const removeAdjustment = useMutation({
    mutationFn: (adjustmentId: number) => deleteAdjustment(adjustmentId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: detailKey })
      toast.success(t('commissionSettlements.adjustment.deleted'))
      onChanged()
    },
    onError,
  })

  const removeDocument = useMutation({
    mutationFn: () => deleteSettlementDocument(settlementId),
    onSuccess: apply('commissionSettlements.actions.documentRemoved'),
    onError,
  })

  const confirmThen = async (key: 'issue' | 'delete' | 'deleteAdjustment' | 'removeDocument', run: () => void) => {
    const accepted = await confirm({
      title: t(`commissionSettlements.confirm.${key}.title`),
      description: t(`commissionSettlements.confirm.${key}.description`),
      confirmLabel: t(`commissionSettlements.confirm.${key}.confirm`),
      tone: CONFIRM_TONES[key],
    })
    if (accepted) {
      run()
    }
  }

  return {
    isBusy:
      recalculate.isPending ||
      issue.isPending ||
      cancel.isPending ||
      saveNotes.isPending ||
      remove.isPending ||
      removeAdjustment.isPending ||
      removeDocument.isPending,
    isCancelling: cancel.isPending,
    recalculate: () => recalculate.mutate(),
    issue: () => void confirmThen('issue', () => issue.mutate()),
    remove: () => void confirmThen('delete', () => remove.mutate()),
    removeAdjustment: (adjustmentId: number) =>
      void confirmThen('deleteAdjustment', () => removeAdjustment.mutate(adjustmentId)),
    removeDocument: () => void confirmThen('removeDocument', () => removeDocument.mutate()),
    cancel: (reason: string, onDone: () => void) => cancel.mutate(reason, { onSuccess: onDone }),
    saveNotes: (notes: string | null) => saveNotes.mutateAsync(notes),
  }
}

export type SettlementDetailActions = ReturnType<typeof useSettlementDetailActions>
