/* eslint-disable react-refresh/only-export-components -- module registry adapter: exports the `moduleScreen` descriptor alongside its screen components (spec 0042 pattern, same as `project-screens.tsx`) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRole, roleDetailQueryKey } from '@/features/roles/api'
import { RoleForm } from '@/features/roles/role-form'
import { RoleDetailView } from '@/features/roles/role-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
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
 * chrome.
 *
 * Spec 0230: the detail edits its rows in place, so `onEdit` is never used;
 * each save reports through `onChanged`.
 */
export function RoleDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: role,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(roleDetailQueryKey(id), () => fetchRole(id))

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
    return <DetailLoading />
  }

  return <RoleDetailView role={role} onChanged={onChanged} />
}

/**
 * Create only (spec 0230): there is no edit form, and the registry generates
 * no `:id/edit` route. Leaving a role being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function RoleFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('roles.form.leaveConfirm.title'),
    description: t('roles.form.leaveConfirm.description'),
    confirmLabel: t('roles.form.leaveConfirm.confirm'),
    cancelLabel: t('roles.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: RoleDetail) => {
    queryClient.invalidateQueries({ queryKey: roleDetailQueryKey(saved.id) })
    // Saved: the navigation to the new role's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = () => {
    void leaveGuard.confirmLeave().then((leave) => {
      if (leave) {
        onCancel()
      }
    })
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <RoleForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'roles',
  basePath: '/roles',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.roles',
  DetailScreen: RoleDetailScreen,
  FormScreen: RoleFormScreen,
  // The detail IS the edit form (spec 0230): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
