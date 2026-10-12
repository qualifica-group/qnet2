import { useTranslation } from 'react-i18next'
import { DetailError } from '@/components/detail/detail-panel'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProject, projectDetailQueryKey } from '@/features/projects/api'
import { ProjectForm } from '@/features/projects/project-form'
import type { ProjectDetail } from '@/features/projects/types'

interface ProjectDuplicateLoaderProps {
  projectId: number
  onSuccess: (project: ProjectDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized source project before mounting the create
 * form pre-filled from it (row action "duplicate"), so the copy starts from
 * authoritative values rather than the grid row snapshot. The form itself
 * still submits via the create path (`ProjectFormMode: 'duplicate'`).
 * Loading shows the same record skeleton as the create form.
 */
export function ProjectDuplicateLoader({ projectId, onSuccess, onCancel }: ProjectDuplicateLoaderProps) {
  const { t } = useTranslation()
  const {
    data: project,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(projectDetailQueryKey(projectId), () => fetchProject(projectId))

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
    return <RecordFormSkeleton />
  }

  return <ProjectForm source={project} onSuccess={onSuccess} onCancel={onCancel} />
}
