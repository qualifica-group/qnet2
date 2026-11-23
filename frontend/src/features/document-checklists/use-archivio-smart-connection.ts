import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  getArchivioSmartSettings,
  testArchivioSmartConnection,
  updateArchivioSmartSettings,
} from '@/features/document-checklists/api'
import { documentChecklistKeys } from '@/features/document-checklists/query-keys'
import type {
  ArchivioSmartConnectionTest,
  ArchivioSmartSettings,
  UpdateArchivioSmartSettingsPayload,
} from '@/features/document-checklists/types'

/** Connection settings (loaded only while the dialog is open), save and test mutations. */
export function useArchivioSmartConnection(enabled: boolean) {
  const queryClient = useQueryClient()

  const settings = useQuery<ArchivioSmartSettings, AxiosError>({
    queryKey: documentChecklistKeys.settings,
    queryFn: getArchivioSmartSettings,
    enabled,
  })

  const save = useMutation<ArchivioSmartSettings, AxiosError, UpdateArchivioSmartSettingsPayload>({
    mutationFn: updateArchivioSmartSettings,
    onSuccess: (saved) => {
      queryClient.setQueryData(documentChecklistKeys.settings, saved)
    },
  })

  const test = useMutation<ArchivioSmartConnectionTest, AxiosError>({
    mutationFn: testArchivioSmartConnection,
  })

  return { settings, save, test }
}
