import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useRoleFormMeta } from '@/features/roles/use-role-form-meta'
import { RoleFormBody } from '@/features/roles/role-form-body'
import type { RoleDetail, RoleDetailWithPermissions } from '@/features/roles/types'

export type RoleFormMode =
  | { type: 'create' }
  | { type: 'edit'; role: RoleDetailWithPermissions }

interface RoleFormProps {
  mode: RoleFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (role: RoleDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a role.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/roles` — then hands off to `RoleFormBody`, which reads every
 * field from that context via `MetaField`.
 */
export function RoleForm(props: RoleFormProps) {
  const { t } = useTranslation()
  const meta = useRoleFormMeta(props.mode)

  if (meta.status === 'loading') {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
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
      <RoleFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
