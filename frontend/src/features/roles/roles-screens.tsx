/* eslint-disable react-refresh/only-export-components -- module registry adapter: exports the `moduleScreen` descriptor alongside its screen components (spec 0042 pattern, same as `project-screens.tsx`) */
import { useTranslation } from 'react-i18next'
import { DetailError } from '@/components/detail/detail-panel'
import { useQueryClient } from '@tanstack/react-query'
import { Skeleton } from '@/components/ui/skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRole } from '@/features/roles/api'
import { RoleForm } from '@/features/roles/role-form'
import { RoleDetailView } from '@/features/roles/role-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { RoleDetail } from '@/features/roles/types'

/**
 * Content-only `roles` screens for the module registry (spec 0042). Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`), which own the surrounding
 * chrome. `RoleDetailView` already owns its own fetch/loading/error, so this
 * screen only forwards the id.
 */
export function RoleDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  return <RoleDetailView roleId={id} onEdit={onEdit} />
}

export function RoleFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: RoleDetail) => {
    queryClient.invalidateQueries({ queryKey: ['roles', 'detail', saved.id] })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <RoleForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <EditRoleLoader roleId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface EditRoleLoaderProps {
  roleId: number
  onSuccess: (role: RoleDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized role detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot. Moved verbatim from `RolesTable`'s inline loader, which
 * the rewire removed.
 */
function EditRoleLoader({ roleId, onSuccess, onCancel }: EditRoleLoaderProps) {
  const { t } = useTranslation()
  const {
    data: role,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(['roles', 'detail', roleId], () => fetchRole(roleId))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('roles.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !role) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <RoleForm mode={{ type: 'edit', role }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'roles',
  basePath: '/roles',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.roles',
  DetailScreen: RoleDetailScreen,
  FormScreen: RoleFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button.
  detailOwnsEditAction: true,
}
