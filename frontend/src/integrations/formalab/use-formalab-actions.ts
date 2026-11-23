import { useCallback } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { startFormalabSync, testFormalabConnection } from '@/integrations/formalab/api'
import { serverMessage } from '@/integrations/formalab/error-message'
import { formalabKeys } from '@/integrations/formalab/query-keys'

/** The sync is queued (202): give the job a moment before rereading the status. */
export const SYNC_REFETCH_DELAY_MS = 3000

/** The three super-admin actions of the status page, each with its own toast. */
export function useFormalabActions() {
  const { t } = useTranslation('formalab')
  const queryClient = useQueryClient()
  const refreshStatus = useCallback(
    () => queryClient.invalidateQueries({ queryKey: formalabKeys.status }),
    [queryClient],
  )

  const sync = useMutation({
    mutationFn: startFormalabSync,
    onSuccess: () => {
      toast.success(t('actions.syncQueued'))
      window.setTimeout(() => void refreshStatus(), SYNC_REFETCH_DELAY_MS)
    },
    onError: (error) => toast.error(serverMessage(error, t('actions.syncError'))),
  })

  const testConnection = useMutation({
    mutationFn: testFormalabConnection,
    onSuccess: (result) => {
      if (result.ok) {
        toast.success(t('actions.testOk', { ms: result.latency_ms ?? 0 }))
      } else {
        toast.error(result.message ?? t('actions.testError'))
      }
      void refreshStatus()
    },
    onError: (error) => toast.error(serverMessage(error, t('actions.testError'))),
  })

  return { sync, testConnection }
}
