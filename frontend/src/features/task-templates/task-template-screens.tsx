/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskTemplate } from '@/features/task-templates/api'
import { TaskTemplateForm } from '@/features/task-templates/task-template-form'
import { TaskTemplateDetailView } from '@/features/task-templates/task-template-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskTemplateDetail } from '@/features/task-templates/types'

/** Query key for a single task template's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['task-templates', 'detail', id] as const
}

/**
 * Content-only `task-templates` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused as-is
 * by the modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`). Mirrors `ProductTypologyDetailScreen`/
 * `ProductTypologyFormScreen`.
 */
export function TaskTemplateDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskTemplate,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchTaskTemplate(id))

  if (isError) {
    return (
      <DetailError
        message={t('taskTemplates.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskTemplate) {
    return <DetailLoading />
  }

  return <TaskTemplateDetailView taskTemplate={taskTemplate} onEdit={onEdit} />
}

export function TaskTemplateFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: TaskTemplateDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <TaskTemplateForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return (
    <TaskTemplateEditScreen taskTemplateId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface TaskTemplateEditScreenProps {
  taskTemplateId: number
  onSuccess: (taskTemplate: TaskTemplateDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized task template detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function TaskTemplateEditScreen({ taskTemplateId, onSuccess, onCancel }: TaskTemplateEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskTemplate,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(taskTemplateId), () => fetchTaskTemplate(taskTemplateId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('taskTemplates.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !taskTemplate) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <TaskTemplateForm mode={{ type: 'edit', taskTemplate }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-templates',
  basePath: '/task-templates',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskTemplates',
  DetailScreen: TaskTemplateDetailScreen,
  FormScreen: TaskTemplateFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}
