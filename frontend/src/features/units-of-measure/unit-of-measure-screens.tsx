/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchUnitOfMeasure } from '@/features/units-of-measure/api'
import { UnitOfMeasureForm } from '@/features/units-of-measure/unit-of-measure-form'
import { UnitOfMeasureDetailView } from '@/features/units-of-measure/unit-of-measure-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { UnitOfMeasureDetail } from '@/features/units-of-measure/types'

/** Query key for a single unit of measure's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['units-of-measure', 'detail', id] as const
}

/**
 * Content-only `units-of-measure` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function UnitOfMeasureDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: unitOfMeasure,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchUnitOfMeasure(id))

  if (isError) {
    return (
      <DetailError
        message={t('unitsOfMeasure.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !unitOfMeasure) {
    return <DetailLoading />
  }

  return <UnitOfMeasureDetailView unitOfMeasure={unitOfMeasure} />
}

export function UnitOfMeasureFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: UnitOfMeasureDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <UnitOfMeasureForm
        mode={{ type: 'create' }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <UnitOfMeasureEditScreen
      unitOfMeasureId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface UnitOfMeasureEditScreenProps {
  unitOfMeasureId: number
  onSuccess: (unitOfMeasure: UnitOfMeasureDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized unit of measure detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values
 * rather than a stale snapshot.
 */
function UnitOfMeasureEditScreen({
  unitOfMeasureId,
  onSuccess,
  onCancel,
}: UnitOfMeasureEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: unitOfMeasure,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(unitOfMeasureId), () => fetchUnitOfMeasure(unitOfMeasureId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('unitsOfMeasure.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !unitOfMeasure) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <UnitOfMeasureForm
      mode={{ type: 'edit', unitOfMeasure }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'units-of-measure',
  basePath: '/units-of-measure',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.unitsOfMeasure',
  DetailScreen: UnitOfMeasureDetailScreen,
  FormScreen: UnitOfMeasureFormScreen,
}
