/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchSector } from '@/features/sectors/api'
import { sectorKeys } from '@/features/sectors/query-keys'
import { SectorForm } from '@/features/sectors/sector-form'
import { SectorDetailView } from '@/features/sectors/sector-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { SectorDetail } from '@/features/sectors/types'

/**
 * Content-only `sectors` screens for the module registry (spec 0042): fetch
 * + the existing presentational view/form, no page chrome. Reused as-is by
 * the modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from `SectorsTable`'s
 * inline loaders, which the rewire removed. Create always starts with no
 * pre-selected parent: the registry's generic form-mode contract carries no
 * `parentId`, so the "add sub-sector" tree affordance stays out of scope here.
 */
export function SectorDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: sector,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(sectorKeys.detail(id), () => fetchSector(id))

  if (isError) {
    return (
      <DetailError
        message={t('sectors.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !sector) {
    return <DetailLoading />
  }

  return <SectorDetailView sector={sector} onEdit={onEdit} />
}

export function SectorFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: SectorDetail) => {
    queryClient.setQueryData(sectorKeys.detail(saved.id), saved)
    void queryClient.invalidateQueries({ queryKey: sectorKeys.tree })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <SectorForm
        mode={{ type: 'create', parentId: null }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return <SectorEditScreen sectorId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface SectorEditScreenProps {
  sectorId: number
  onSuccess: (sector: SectorDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized sector detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function SectorEditScreen({ sectorId, onSuccess, onCancel }: SectorEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: sector,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(sectorKeys.detail(sectorId), () => fetchSector(sectorId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('sectors.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !sector) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <SectorForm mode={{ type: 'edit', sector }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'sectors',
  basePath: '/sectors',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.sectors',
  DetailScreen: SectorDetailScreen,
  FormScreen: SectorFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}