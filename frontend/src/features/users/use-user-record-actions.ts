import axios from 'axios'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import { useAuth } from '@/features/auth/use-auth'
import { deleteUser, updateUser, userDetailQueryKey } from '@/features/users/api'
import { backendErrorMessage } from '@/features/users/user-action-errors'
import { useResendWelcomeEmail } from '@/features/users/use-resend-welcome-email'
import type { UserDetailWithPermissions } from '@/features/users/types'

/** Where the actor lands after "Impersona": the same page the grid's row action opens. */
const IMPERSONATION_LANDING_PATH = '/dashboard'

/** Why the detail must be left: the record is gone, or the session now IS that user. */
export type UserLeaveReason = 'deleted' | 'impersonated'

interface UserRecordActionCallbacks {
  /** After an in-place change (status), so the host refreshes whatever lists the user. */
  onChanged?: () => void
  /** After the record was deleted or impersonated: the host closes its surface. */
  onLeave?: (reason: UserLeaveReason) => void
}

/**
 * Orchestrates the record actions of the user detail: resend the welcome
 * email, activate/deactivate, "Impersona" and delete. Each negative or
 * identity-swapping action asks first through the app's `useConfirm`;
 * activation does not (it only restores access). The backend re-authorizes
 * every call: the bar only decides what to offer.
 */
export function useUserRecordActions(user: UserDetailWithPermissions, callbacks: UserRecordActionCallbacks) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { impersonate } = useAuth()
  const { onChanged, onLeave } = callbacks
  const resendWelcome = useResendWelcomeEmail(user.id)

  const toggleActive = useMutation({
    mutationFn: (isActive: boolean) => updateUser(user.id, { is_active: isActive }),
    onSuccess: (saved, requested) => {
      // Spec 0272 D-8: a past termination date keeps the user inactive whatever was sent.
      if (requested && !saved.is_active) {
        toast.warning(t('users.detail.actions.stillInactive'))
      } else {
        toast.success(t(requested ? 'users.detail.actions.activated' : 'users.detail.actions.deactivated'))
      }
      void queryClient.invalidateQueries({ queryKey: userDetailQueryKey(user.id) })
      onChanged?.()
    },
    onError: (error) => toast.error(backendErrorMessage(error) ?? t('users.detail.actions.toggleError')),
  })

  const remove = useMutation({
    mutationFn: () => deleteUser(user.id),
    onSuccess: () => {
      toast.success(t('users.form.deleted'))
      onChanged?.()
      onLeave?.('deleted')
    },
    onError: (error) => toast.error(deleteErrorMessage(t, error)),
  })

  const startImpersonation = useMutation({
    mutationFn: () => impersonate(user.id),
    onSuccess: () => {
      onLeave?.('impersonated')
      void navigate(IMPERSONATION_LANDING_PATH)
    },
    onError: () => toast.error(t('impersonation.startError')),
  })

  const activate = () => toggleActive.mutate(true)

  const deactivate = async () => {
    const confirmed = await confirm({
      tone: 'warning',
      title: t('users.detail.actions.deactivateConfirm.title', { name: user.name }),
      description: t('users.detail.actions.deactivateConfirm.description'),
      confirmLabel: t('users.detail.actions.deactivate'),
    })
    if (confirmed) {
      toggleActive.mutate(false)
    }
  }

  const impersonateUser = async () => {
    const confirmed = await confirm({
      title: t('users.detail.actions.impersonateConfirm.title', { name: user.name }),
      description: t('users.detail.actions.impersonateConfirm.description'),
      confirmLabel: t('actions.impersonate'),
    })
    if (confirmed) {
      startImpersonation.mutate()
    }
  }

  const deleteRecord = async () => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('users.detail.actions.deleteConfirm.title', { name: user.name }),
      description: t('users.detail.actions.deleteConfirm.description'),
      confirmLabel: t('actions.delete'),
    })
    if (confirmed) {
      remove.mutate()
    }
  }

  return {
    resendWelcome: () => resendWelcome.mutate(),
    activate,
    deactivate: () => void deactivate(),
    impersonate: () => void impersonateUser(),
    delete: () => void deleteRecord(),
    isResending: resendWelcome.isPending,
    isTogglingActive: toggleActive.isPending,
    isImpersonating: startImpersonation.isPending,
    isDeleting: remove.isPending,
  }
}

/** 403/422 = refused (policy, last super-admin), 409 = still referenced by leads/opportunities. */
function deleteErrorMessage(t: (key: string) => string, error: unknown): string {
  const status = axios.isAxiosError(error) ? error.response?.status : undefined
  if (status === 403 || status === 422) {
    return t('users.form.deleteForbidden')
  }
  if (status === 409) {
    return t('users.detail.actions.deleteInUse')
  }
  return t('users.form.deleteError')
}
