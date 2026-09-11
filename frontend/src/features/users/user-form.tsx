import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useUserFormMeta } from '@/features/users/use-user-form-meta'
import { UserFormBody } from '@/features/users/user-form-body'
import type { UserDetail, UserDetailWithPermissions } from '@/features/users/types'

export type UserFormMode =
  | { type: 'create' }
  | { type: 'edit'; user: UserDetailWithPermissions }

interface UserFormProps {
  mode: UserFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (user: UserDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
  /**
   * EDIT mode only: called after an immediate avatar upload/remove succeeds, so
   * the caller can refresh the users grid without closing the form.
   */
  onAvatarChange?: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a user.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/users` — then hands off to `UserFormBody`, which reads every
 * field/action from that context via `MetaField`/`useResourcePermissions()`.
 */
export function UserForm(props: UserFormProps) {
  const { t } = useTranslation()
  const meta = useUserFormMeta(props.mode)

  if (meta.status === 'loading') {
    return <RecordFormSkeleton />
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <UserFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
