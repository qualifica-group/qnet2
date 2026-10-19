import { useTranslation } from 'react-i18next'
import { Send, Trash2, UserCheck, UserX, VenetianMask } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/features/auth/use-auth'
import { ACTION_BUTTON_VARIANT } from '@/features/table/action-tone'
import { useUserRecordActions, type UserLeaveReason } from '@/features/users/use-user-record-actions'
import type { UserDetailWithPermissions } from '@/features/users/types'

interface UserActionsBarProps {
  user: UserDetailWithPermissions
  onChanged?: () => void
  onLeave?: (reason: UserLeaveReason) => void
}

/**
 * The user record's domain actions, between the identity band and the KPI
 * strip — the same bar the task and contract records carry. Each button is
 * absent (never merely disabled) when it cannot apply: the server flags
 * (`permissions.*`) decide who may, the record state decides when. The actor's
 * own record never offers deactivation (it would lock them out); delete and
 * impersonate already exclude it server-side.
 */
export function UserActionsBar({ user, onChanged, onLeave }: UserActionsBarProps) {
  const { t } = useTranslation()
  const { user: currentUser, impersonator } = useAuth()
  const actions = useUserRecordActions(user, { onChanged, onLeave })
  const { resource, fields, actions: allowed } = user.permissions
  const isSelf = currentUser?.id === user.id

  const canResend = user.must_set_password && resource.update
  // A field missing from the block resolves permissive, as `useResourcePermissions` does: the PATCH re-authorizes.
  const canToggleActive = !isSelf && resource.update && (fields.is_active?.editable ?? true)
  // Nested impersonation is refused server-side: no "Impersona" while already operating as someone.
  const canImpersonate = Boolean(allowed.impersonate) && impersonator === null
  const canDelete = Boolean(allowed.delete)

  if (!canResend && !canToggleActive && !canImpersonate && !canDelete) {
    return null
  }

  return (
    <div className="flex flex-wrap items-center gap-2 border-y bg-muted/40 px-4 py-3">
      {canResend ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.action}
          className="bg-card"
          size="sm"
          disabled={actions.isResending}
          onClick={actions.resendWelcome}
        >
          <Send aria-hidden="true" />
          {t('users.detail.resendWelcome')}
        </Button>
      ) : null}

      {canToggleActive && !user.is_active ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.success}
          size="sm"
          disabled={actions.isTogglingActive}
          onClick={actions.activate}
        >
          <UserCheck aria-hidden="true" />
          {t('users.detail.actions.activate')}
        </Button>
      ) : null}

      {canToggleActive && user.is_active ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.danger}
          size="sm"
          disabled={actions.isTogglingActive}
          onClick={actions.deactivate}
        >
          <UserX aria-hidden="true" />
          {t('users.detail.actions.deactivate')}
        </Button>
      ) : null}

      {canImpersonate ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.action}
          className="bg-card"
          size="sm"
          disabled={actions.isImpersonating}
          onClick={actions.impersonate}
        >
          <VenetianMask aria-hidden="true" />
          {t('actions.impersonate')}
        </Button>
      ) : null}

      {canDelete ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.danger}
          size="sm"
          disabled={actions.isDeleting}
          onClick={actions.delete}
        >
          <Trash2 aria-hidden="true" />
          {t('actions.delete')}
        </Button>
      ) : null}
    </div>
  )
}
