/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchAttribute } from '@/features/attributes/api'
import { AttributeForm } from '@/features/attributes/attribute-form'
import { AttributeDetailView } from '@/features/attributes/attribute-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { AttributeDetail } from '@/features/attributes/types'

/** Query key for a single attribute's detail (fresh-on-open pattern), moved verbatim from `AttributesTable`. */
function detailQueryKey(id: number) {
  return ['attributes', 'detail', id] as const
}

/**
 * Content-only `attributes` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `AttributesTable`'s inline loaders, which the rewire removed.
 */
export function AttributeDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: attribute,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchAttribute(id))

  if (isError) {
    return (
      <DetailError
        message={t('attributes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !attribute) {
    return <DetailLoading />
  }

  return <AttributeDetailView attribute={attribute} />
}

export function AttributeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: AttributeDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <AttributeForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return (
    <AttributeLoadedFormScreen
      attributeId={mode.id}
      variant={mode.type}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface AttributeLoadedFormScreenProps {
  attributeId: number
  /** `edit` patches the loaded attribute; `duplicate` creates a copy pre-filled from it. */
  variant: 'edit' | 'duplicate'
  onSuccess: (attribute: AttributeDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized attribute detail before mounting the form,
 * so the partial PATCH (edit) or the copy (duplicate) starts from
 * authoritative values rather than a stale snapshot.
 */
function AttributeLoadedFormScreen({
  attributeId,
  variant,
  onSuccess,
  onCancel,
}: AttributeLoadedFormScreenProps) {
  const { t } = useTranslation()
  const {
    data: attribute,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(attributeId), () => fetchAttribute(attributeId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('attributes.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !attribute) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <AttributeForm
      mode={variant === 'edit' ? { type: 'edit', attribute } : { type: 'duplicate', source: attribute }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'attributes',
  basePath: '/attributes',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.attributes',
  DetailScreen: AttributeDetailScreen,
  FormScreen: AttributeFormScreen,
}