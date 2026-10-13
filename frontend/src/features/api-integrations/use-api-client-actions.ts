import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import { useDeleteApiClient, useRotateApiClientKey } from '@/features/api-integrations/use-api-clients'
import type { ApiKeyReveal } from '@/features/api-integrations/components/api-key-dialog'

interface UseApiClientActionsOptions {
  onKeyIssued: (reveal: ApiKeyReveal) => void
  onChanged: () => void
}

/**
 * Rotate and revoke flows shared by the table row actions and the edit
 * dialog: confirm, mutate, toast, then hand the fresh key (rotation) to the
 * caller's local state. The key is never read from the mutation result again.
 */
export function useApiClientActions({ onKeyIssued, onChanged }: UseApiClientActionsOptions) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const rotateMutation = useRotateApiClientKey()
  const deleteMutation = useDeleteApiClient()

  const rotate = useCallback(
    async (id: number, name: string) => {
      // Step 1: ask for confirmation
      const confirmed = await confirm({
        title: t('apiIntegrations.actions.rotateConfirmTitle', { name }),
        description: t('apiIntegrations.actions.rotateConfirmDescription'),
        confirmLabel: t('apiIntegrations.actions.rotate'),
        tone: 'warning',
      })
      if (!confirmed) {
        return
      }
      // Step 2: rotate and move the key out of the mutation cache
      try {
        const result = await rotateMutation.mutateAsync(id)
        rotateMutation.reset()
        onKeyIssued({ clientName: result.client.name, plainTextKey: result.plain_text_key, rotated: true })
        onChanged()
      } catch {
        toast.error(t('apiIntegrations.actions.rotateError'))
      }
    },
    [confirm, onChanged, onKeyIssued, rotateMutation, t],
  )

  const revoke = useCallback(
    async (id: number, name: string) => {
      const confirmed = await confirm({
        title: t('apiIntegrations.actions.revokeConfirmTitle', { name }),
        description: t('apiIntegrations.actions.revokeConfirmDescription'),
        confirmLabel: t('apiIntegrations.actions.revoke'),
        tone: 'destructive',
      })
      if (!confirmed) {
        return
      }
      try {
        await deleteMutation.mutateAsync(id)
        toast.success(t('apiIntegrations.actions.revoked'))
        onChanged()
      } catch {
        toast.error(t('apiIntegrations.actions.revokeError'))
      }
    },
    [confirm, deleteMutation, onChanged, t],
  )

  return { rotate, revoke }
}
