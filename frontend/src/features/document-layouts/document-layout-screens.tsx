/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchDocumentLayout } from '@/features/document-layouts/api'
import { DocumentLayoutForm } from '@/features/document-layouts/document-layout-form'
import { DocumentLayoutDetailView } from '@/features/document-layouts/document-layout-detail'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { DocumentLayoutDetail } from '@/features/document-layouts/types'

/** Query key for a single document layout's detail (fresh-on-open pattern). */
export function detailQueryKey(id: number) {
  return ['document-layouts', 'detail', id] as const
}

/**
 * Content-only `document-layouts` screens for the module registry (spec
 * 0069): fetch + the existing presentational view/form, no page chrome.
 * `document-layouts` uses `OPEN_MODE_PAGE` (D-9: the visual editor needs the
 * width of a canvas + inspector + variables panel, it does not fit a modal
 * Sheet) WITHOUT `generateRoutes: false` — like `quotes`, this module has no
 * bespoke page of its own: the generic `ModuleDetailPage`/`ModuleFormPage`
 * (from `buildModuleRoutes()`) host these screens directly for
 * `new`/`:id`/`:id/edit`; only the list route (`/document-layouts`) stays
 * declared by hand in `router.tsx`. These screens render only the METADATA
 * form (name/code/description/module/active/default, wave 1) — editing the
 * block/zone `config` is the visual editor's own surface (wave 2, a separate
 * owner), mounted elsewhere.
 */
export function DocumentLayoutDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: documentLayout,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchDocumentLayout(id))

  if (isError) {
    return (
      <DetailError
        message={t('documentLayouts.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !documentLayout) {
    return <DetailLoading />
  }

  return <DocumentLayoutDetailView documentLayout={documentLayout} />
}

export function DocumentLayoutFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: DocumentLayoutDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <DocumentLayoutForm
        mode={{ type: 'create' }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <DocumentLayoutEditScreen
      documentLayoutId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface DocumentLayoutEditScreenProps {
  documentLayoutId: number
  onSuccess: (documentLayout: DocumentLayoutDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized document layout detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values
 * rather than a stale snapshot.
 */
function DocumentLayoutEditScreen({
  documentLayoutId,
  onSuccess,
  onCancel,
}: DocumentLayoutEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: documentLayout,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(documentLayoutId), () => fetchDocumentLayout(documentLayoutId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('documentLayouts.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !documentLayout) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <DocumentLayoutForm
      mode={{ type: 'edit', documentLayout }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'document-layouts',
  basePath: '/document-layouts',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.documentLayouts',
  DetailScreen: DocumentLayoutDetailScreen,
  FormScreen: DocumentLayoutFormScreen,
}
