import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import {
  deleteStaffTraining,
  deleteStaffTrainingAttachment,
  downloadStaffTrainingAttachment,
  uploadStaffTrainingAttachment,
} from '@/features/staff-trainings/api'
import { staffTrainingKeys } from '@/features/staff-trainings/query-keys'
import { useStaffTrainingRefresh } from '@/features/staff-trainings/use-staff-training-refresh'
import { saveBlob } from '@/lib/download'

/**
 * Every write the detail offers on one training: delete, upload/replace/remove the certificate and
 * download it. Each success refreshes the detail, the situation and the grid; failures show the
 * envelope message.
 */
export function useStaffTrainingActions(id: number, refreshGrid?: () => void, onDeleted?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const refresh = useStaffTrainingRefresh(refreshGrid)

  const succeed = (messageKey: string) => {
    refresh(id)
    toast.success(t(messageKey))
  }
  const fail = (error: unknown) => toast.error(serverMessage(error) ?? t('staffTrainings.actions.error'))

  const remove = useMutation({
    mutationFn: () => deleteStaffTraining(id),
    onSuccess: () => {
      // The detail would 404 now: drop it instead of refetching it
      queryClient.removeQueries({ queryKey: staffTrainingKeys.detail(id) })
      void queryClient.invalidateQueries({ queryKey: staffTrainingKeys.complianceAll })
      refreshGrid?.()
      toast.success(t('staffTrainings.actions.deleted'))
      onDeleted?.()
    },
    onError: fail,
  })
  const upload = useMutation({
    mutationFn: (file: File) => uploadStaffTrainingAttachment(id, file),
    onSuccess: () => succeed('staffTrainings.actions.attachmentSaved'),
    onError: fail,
  })
  const removeAttachment = useMutation({
    mutationFn: () => deleteStaffTrainingAttachment(id),
    onSuccess: () => succeed('staffTrainings.actions.attachmentRemoved'),
    onError: fail,
  })
  const download = useMutation({
    mutationFn: async (fallbackName: string) => {
      const file = await downloadStaffTrainingAttachment(id)
      saveBlob(file.blob, file.filename ?? fallbackName)
    },
    onError: () => toast.error(t('staffTrainings.actions.downloadError')),
  })

  return { remove, upload, removeAttachment, download }
}

export type StaffTrainingActions = ReturnType<typeof useStaffTrainingActions>
