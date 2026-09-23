/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTag } from '@/features/tags/api'
import { TagForm } from '@/features/tags/tag-form'
import { TagDetailView } from '@/features/tags/tag-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TagDetail } from '@/features/tags/types'

/** Query key for a single tag's detail (fresh-on-open pattern). Same shape used by `use-tag-form.ts`'s own cache write. */
function detailQueryKey(id: number) {
  return ['tags', 'detail', id] as const
}

/**
 * Content-only `tags` screens for the module registry (spec 0042): fetch +
 * the existing presentational view/form, no page chrome. Reused as-is by
 * the modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from `TagsTable`'s
 * inline loaders, which the rewire removed.
 */
export function TagDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: tag,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchTag(id))

  if (isError) {
    return (
      <DetailError
        message={t('tags.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !tag) {
    return <DetailLoading />
  }

  return <TagDetailView tag={tag} onEdit={onEdit} />
}

export function TagFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: TagDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <TagForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <TagEditScreen tagId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface TagEditScreenProps {
  tagId: number
  onSuccess: (tag: TagDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized tag detail before mounting the edit form,
 * so the partial PATCH starts from authoritative values rather than a stale
 * snapshot.
 */
function TagEditScreen({ tagId, onSuccess, onCancel }: TagEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: tag,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(tagId), () => fetchTag(tagId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('tags.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !tag) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <TagForm mode={{ type: 'edit', tag }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'tags',
  basePath: '/tags',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.tags',
  DetailScreen: TagDetailScreen,
  FormScreen: TagFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}