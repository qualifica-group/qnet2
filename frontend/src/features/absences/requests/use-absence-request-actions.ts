import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  approveAbsenceRequest,
  cancelAbsenceRequest,
  deleteAbsenceAttachment,
  deleteAbsenceRequest,
  downloadAbsenceAttachment,
  rejectAbsenceRequest,
  submitAbsenceRequest,
  uploadAbsenceAttachment,
} from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { useAbsenceRefresh } from '@/features/absences/requests/use-absence-refresh'
import { saveBlob } from '@/lib/download'

/**
 * Every write the detail offers on one request. Each success refreshes the
 * detail, the balances and the grid; failures other than the reject 422 (which
 * the dialog wires to its field) show the envelope message.
 */
export function useAbsenceRequestActions(id: number, refreshGrid?: () => void, onDeleted?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const refresh = useAbsenceRefresh(refreshGrid)

  const succeed = (messageKey: string) => {
    refresh(id)
    toast.success(t(messageKey))
  }
  const fail = (error: unknown) => toast.error(serverMessage(error) ?? t('absences.requests.actions.error'))

  const submit = useMutation({
    mutationFn: () => submitAbsenceRequest(id),
    onSuccess: () => succeed('absences.requests.actions.submitted'),
    onError: fail,
  })
  const approve = useMutation({
    mutationFn: () => approveAbsenceRequest(id),
    onSuccess: () => succeed('absences.requests.actions.approved'),
    onError: fail,
  })
  const cancel = useMutation({
    mutationFn: () => cancelAbsenceRequest(id),
    onSuccess: () => succeed('absences.requests.actions.cancelled'),
    onError: fail,
  })
  const reject = useMutation({
    mutationFn: (rejection_reason: string) => rejectAbsenceRequest(id, { rejection_reason }),
    onSuccess: () => succeed('absences.requests.actions.rejected'),
  })
  const remove = useMutation({
    mutationFn: () => deleteAbsenceRequest(id),
    onSuccess: () => {
      // The detail would 404 now: drop it instead of refetching it
      queryClient.removeQueries({ queryKey: absenceKeys.detail(id) })
      void queryClient.invalidateQueries({ queryKey: absenceKeys.balancesAll })
      refreshGrid?.()
      toast.success(t('absences.requests.actions.deleted'))
      onDeleted?.()
    },
    onError: fail,
  })
  const upload = useMutation({
    mutationFn: (file: File) => uploadAbsenceAttachment(id, file),
    onSuccess: () => succeed('absences.requests.actions.attachmentSaved'),
    onError: fail,
  })
  const removeAttachment = useMutation({
    mutationFn: () => deleteAbsenceAttachment(id),
    onSuccess: () => succeed('absences.requests.actions.attachmentRemoved'),
    onError: fail,
  })
  const download = useMutation({
    mutationFn: async (fallbackName: string) => {
      const file = await downloadAbsenceAttachment(id)
      saveBlob(file.blob, file.filename ?? fallbackName)
    },
    onError: () => toast.error(t('absences.requests.actions.downloadError')),
  })

  return { submit, approve, cancel, reject, remove, upload, removeAttachment, download }
}

export type AbsenceRequestActions = ReturnType<typeof useAbsenceRequestActions>
