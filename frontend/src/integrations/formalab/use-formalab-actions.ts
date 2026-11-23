import { useCallback } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { startFormalabSync, testFormalabConnection } from '@/integrations/formalab/api'
import { serverMessage } from '@/integrations/formalab/error-message'
import { formalabKeys } from '@/integrations/formalab/query-keys'

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
    onSuccess: () => toast.success(t('actions.syncQueued')),
    // 409: a sync is already running; either way the status tells where it stands
    onError: (error) => toast.error(serverMessage(error, t('actions.syncError'))),
    onSettled: () => void refreshStatus(),
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
