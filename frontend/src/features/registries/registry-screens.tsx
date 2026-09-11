/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRegistry, registryDetailQueryKey } from '@/features/registries/api'
import { RegistryForm } from '@/features/registries/registry-form'
import { RegistryDetailView } from '@/features/registries/registry-detail'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { RegistryDetail } from '@/features/registries/types'

/**
 * Content-only `registries` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Unlike the
 * modal-native modules, `registries` defaults to its bespoke dedicated pages
 * (spec 0022, `RegistryDetailPage`/`RegistryFormPage`) and does NOT get
 * generated routes (`generateRoutes: false`) — these screens only back the
 * 'modal' alternative a user can opt into (spec 0042).
 */
export function RegistryDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: registry,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(registryDetailQueryKey(id), () => fetchRegistry(id))

  if (isError) {
    return (
      <DetailError
        message={t('registries.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !registry) {
    return <DetailLoading />
  }

  return <RegistryDetailView registry={registry} onEdit={onEdit} />
}

export function RegistryFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: RegistryDetail) => {
    queryClient.invalidateQueries({ queryKey: registryDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <RegistryForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <RegistryEditScreen registryId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface RegistryEditScreenProps {
  registryId: number
  onSuccess: (registry: RegistryDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized registry detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function RegistryEditScreen({ registryId, onSuccess, onCancel }: RegistryEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: registry,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(registryDetailQueryKey(registryId), () => fetchRegistry(registryId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('registries.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !registry) {
    return <RecordFormSkeleton />
  }

  return <RegistryForm mode={{ type: 'edit', registry }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'registries',
  basePath: '/registries',
  defaultMode: OPEN_MODE_PAGE,
  generateRoutes: false,
  labelKey: 'navigation.registries',
  DetailScreen: RegistryDetailScreen,
  FormScreen: RegistryFormScreen,
  // The record card renders its own Edit action, and the form its own identity
  // bar — same registration Opportunità carries, so the hosts must not stack a
  // second heading or button on top of them.
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}