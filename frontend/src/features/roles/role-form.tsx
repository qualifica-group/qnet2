import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { RoleFormBody } from '@/features/roles/role-form-body'
import type { RoleDetail } from '@/features/roles/types'

interface RoleFormProps {
  /** Called after a successful create so the caller can close + refresh. */
  onSuccess: (role: RoleDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The role create form (spec 0230: there is no edit form, the detail edits
 * in place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/roles`) before rendering `RoleFormBody`,
 * which reads every field from that context via `MetaField`.
 */
export function RoleForm(props: RoleFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('roles')

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!metaQuery.data) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <RoleFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
