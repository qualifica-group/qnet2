/* eslint-disable react-refresh/only-export-components -- module registry adapter: exports the `moduleScreen` descriptor alongside its screen components (spec 0042 pattern, same as `project-screens.tsx`) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { userDetailQueryKey } from '@/features/users/api'
import { UserForm } from '@/features/users/user-form'
import { UserDetailView } from '@/features/users/user-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { UserDetail } from '@/features/users/types'

/**
 * Content-only `users` screens for the module registry (spec 0042). Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`), which own the surrounding
 * chrome. `UserDetailView` owns its own fetch, so this screen only forwards
 * the id.
 *
 * Spec 0222: the detail edits its fields in place, so `onEdit` is never used;
 * each save reports through `onChanged`.
 */
export function UserDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  return <UserDetailView userId={id} onChanged={onChanged} />
}

/**
 * Create only (spec 0222): there is no edit form, and the registry generates
 * no `:id/edit` route. Leaving a user being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function UserFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('users.form.leaveConfirm.title'),
    description: t('users.form.leaveConfirm.description'),
    confirmLabel: t('users.form.leaveConfirm.confirm'),
    cancelLabel: t('users.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: UserDetail) => {
    queryClient.invalidateQueries({ queryKey: userDetailQueryKey(saved.id) })
    // Saved: the navigation to the new user's detail is no "leaving".
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
      <UserForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'users',
  basePath: '/users',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.users',
  DetailScreen: UserDetailScreen,
  FormScreen: UserFormScreen,
  // The detail IS the edit form (spec 0222): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
