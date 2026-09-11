/* eslint-disable react-refresh/only-export-components -- module registry adapter: exports the `moduleScreen` descriptor alongside its screen components (spec 0042 pattern, same as `project-screens.tsx`) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchUser } from '@/features/users/api'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { UserForm } from '@/features/users/user-form'
import { UserDetailView } from '@/features/users/user-detail'
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
 * chrome. `UserDetailView` already owns its own fetch/loading/error (unlike
 * `projects`, whose presentational view takes already-loaded data), so this
 * screen only forwards the id.
 */
export function UserDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  return <UserDetailView userId={id} onEdit={onEdit} />
}

export function UserFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: UserDetail) => {
    queryClient.invalidateQueries({ queryKey: ['users', 'detail', saved.id] })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <UserForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <EditUserLoader userId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface EditUserLoaderProps {
  userId: number
  onSuccess: (user: UserDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized user detail before mounting the edit form,
 * so the partial PATCH starts from authoritative values rather than a stale
 * snapshot. Moved verbatim from `UsersTable`'s inline loader, which the
 * rewire removed. `onAvatarChange` is deliberately not wired here: it used to
 * refresh the grid's own row immediately after an avatar upload via the
 * table's imperative ref, which `ModuleFormScreenProps` has no seam for (see
 * handoff — flagged, not invented).
 */
function EditUserLoader({ userId, onSuccess, onCancel }: EditUserLoaderProps) {
  const { t } = useTranslation()
  const {
    data: user,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['users', 'detail', userId], () => fetchUser(userId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('users.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !user) {
    return <RecordFormSkeleton />
  }

  return <UserForm mode={{ type: 'edit', user }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'users',
  basePath: '/users',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.users',
  DetailScreen: UserDetailScreen,
  FormScreen: UserFormScreen,
  // The record card renders its own Edit action, and the form its own identity
  // bar (title/subtitle + actions on one row) — same registration Opportunità
  // carries, so the generic hosts must not stack a second heading or button.
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}
