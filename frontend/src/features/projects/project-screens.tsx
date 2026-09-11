/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProject, projectDetailQueryKey } from '@/features/projects/api'
import { ProjectForm } from '@/features/projects/project-form'
import { ProjectEditLoader } from '@/features/projects/project-edit-loader'
import { ProjectDuplicateLoader } from '@/features/projects/project-duplicate-loader'
import { ProjectDetailView } from '@/features/projects/project-detail'
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
 * chrome. Mirrors what `ProjectsTable`'s inline loaders did before the
 * rewire — no new fetch/view logic, only the reusable seam extracted.
 */
export function ProjectDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: project,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(projectDetailQueryKey(id), () => fetchProject(id))

  if (isError) {
    return (
      <DetailError
        message={t('projects.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !project) {
    return <DetailLoading />
  }

  return <ProjectDetailView project={project} onEdit={onEdit} />
}

export function ProjectFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: ProjectDetail) => {
    queryClient.invalidateQueries({ queryKey: projectDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <ProjectForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  if (mode.type === 'duplicate') {
    return <ProjectDuplicateLoader projectId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <ProjectEditLoader projectId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'projects',
  basePath: '/projects',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.projects',
  DetailScreen: ProjectDetailScreen,
  FormScreen: ProjectFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}