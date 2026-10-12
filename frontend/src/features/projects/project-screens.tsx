/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProject, projectDetailQueryKey } from '@/features/projects/api'
import { ProjectForm } from '@/features/projects/project-form'
import { ProjectDuplicateLoader } from '@/features/projects/project-duplicate-loader'
import { ProjectDetailView } from '@/features/projects/project-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { ProjectDetail } from '@/features/projects/types'

/**
 * Content-only `projects` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`), which own the surrounding
 * chrome.
 *
 * Spec 0220: the detail edits its fields in place, so `onEdit` is never used;
 * each save reports through `onChanged`.
 */
export function ProjectDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: project,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(projectDetailQueryKey(id), () => fetchProject(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('projects.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !project) {
    return <DetailLoading />
  }

  return <ProjectDetailView project={project} onChanged={onChanged} />
}

/**
 * Create and duplicate only (spec 0220): there is no edit form, and the
 * registry generates no `:id/edit` route. Leaving a project being created —
 * Cancel, the Sheet's X/overlay/Esc, a link, a reload — always asks first.
 */
export function ProjectFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('projects.form.leaveConfirm.title'),
    description: t('projects.form.leaveConfirm.description'),
    confirmLabel: t('projects.form.leaveConfirm.confirm'),
    cancelLabel: t('projects.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type === 'edit') {
    return null
  }

  const handleSuccess = (saved: ProjectDetail) => {
    queryClient.invalidateQueries({ queryKey: projectDetailQueryKey(saved.id) })
    // Saved: the navigation to the new project's detail is no "leaving".
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
      {mode.type === 'duplicate' ? (
        <ProjectDuplicateLoader projectId={mode.id} onSuccess={handleSuccess} onCancel={handleCancel} />
      ) : (
        <ProjectForm onSuccess={handleSuccess} onCancel={handleCancel} />
      )}
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'projects',
  basePath: '/projects',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.projects',
  DetailScreen: ProjectDetailScreen,
  FormScreen: ProjectFormScreen,
  // The detail IS the edit form (spec 0220): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
