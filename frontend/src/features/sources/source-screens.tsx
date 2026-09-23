/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchSource } from '@/features/sources/api'
import { SourceForm } from '@/features/sources/source-form'
import { SourceDetailView } from '@/features/sources/source-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { SourceDetail } from '@/features/sources/types'

/** Query key for a single source's detail (fresh-on-open pattern). Same shape used by `use-source-form.ts`'s own cache write. */
function detailQueryKey(id: number) {
  return ['sources', 'detail', id] as const
}

/**
 * Content-only `sources` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `SourcesTable`'s inline loaders, which the rewire removed.
 */
export function SourceDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: source,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchSource(id))

  if (isError) {
    return (
      <DetailError
        message={t('sources.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !source) {
    return <DetailLoading />
  }

  return <SourceDetailView source={source} onEdit={onEdit} />
}

export function SourceFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: SourceDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <SourceForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <SourceEditScreen sourceId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface SourceEditScreenProps {
  sourceId: number
  onSuccess: (source: SourceDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized source detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function SourceEditScreen({ sourceId, onSuccess, onCancel }: SourceEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: source,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(sourceId), () => fetchSource(sourceId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('sources.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !source) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <SourceForm mode={{ type: 'edit', source }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'sources',
  basePath: '/sources',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.sources',
  DetailScreen: SourceDetailScreen,
  FormScreen: SourceFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button.
  detailOwnsEditAction: true,
}