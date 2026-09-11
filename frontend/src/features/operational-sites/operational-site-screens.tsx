/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchOperationalSite } from '@/features/operational-sites/api'
import { OperationalSiteForm } from '@/features/operational-sites/operational-site-form'
import { OperationalSiteDetailView } from '@/features/operational-sites/operational-site-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { OperationalSiteDetail } from '@/features/operational-sites/types'

/** Same detail query key the former `*OperationalSiteLoader`s used. */
function detailQueryKey(id: number) {
  return ['operational-sites', 'detail', id] as const
}

/**
 * Content-only `operational-sites` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`), which own the
 * surrounding chrome. Mirrors what `OperationalSitesTable`'s inline loaders
 * did before the rewire — no new fetch/view logic, only the reusable seam
 * extracted.
 */
export function OperationalSiteDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: operationalSite,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchOperationalSite(id))

  if (isError) {
    return (
      <DetailError
        message={t('operationalSites.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !operationalSite) {
    return <DetailLoading />
  }

  return <OperationalSiteDetailView operationalSite={operationalSite} onEdit={onEdit} />
}

export function OperationalSiteFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (operationalSite: OperationalSiteDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(operationalSite.id) })
    onSuccess(operationalSite.id)
  }

  if (mode.type === 'create') {
    return (
      <OperationalSiteForm
        mode={{ type: 'create' }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <OperationalSiteEditLoader
      operationalSiteId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface OperationalSiteEditLoaderProps {
  operationalSiteId: number
  onSuccess: (operationalSite: OperationalSiteDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized operational site detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values
 * rather than a stale snapshot (moved here unchanged from the former
 * `operational-sites-table.tsx` inline loader).
 */
function OperationalSiteEditLoader({
  operationalSiteId,
  onSuccess,
  onCancel,
}: OperationalSiteEditLoaderProps) {
  const { t } = useTranslation()
  const {
    data: operationalSite,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(operationalSiteId), () =>
    fetchOperationalSite(operationalSiteId),
  )

  if (isError) {
    return (
      <DetailError
        message={t('operationalSites.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !operationalSite) {
    return <DetailLoading />
  }

  return (
    <OperationalSiteForm
      mode={{ type: 'edit', operationalSite }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'operational-sites',
  basePath: '/operational-sites',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.operationalSites',
  DetailScreen: OperationalSiteDetailScreen,
  FormScreen: OperationalSiteFormScreen,
  // The record card renders its own Edit action and the form its own identity
  // bar, so the generic hosts must not stack a second heading or button — the
  // same registration Opportunita', Utenti and Lead carry.
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}