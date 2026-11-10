/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRegistry, registryDetailQueryKey } from '@/features/registries/api'
import { GuardedRegistryForm } from '@/features/registries/guarded-registry-form'
import { RegistryDetailView } from '@/features/registries/registry-detail'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleScreens,
} from '@/features/modules/types'
import type { RegistryDetail } from '@/features/registries/types'

/**
 * Content-only `registries` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Unlike the
 * modal-native modules, `registries` defaults to its bespoke dedicated pages
 * (spec 0022, `RegistryDetailPage`/`RegistryFormPage`) and does NOT get
 * generated routes (`generateRoutes: false`) — these screens only back the
 * 'modal' alternative a user can opt into (spec 0042).
 *
 * Spec 0200: the detail edits its fields in place, so `onEdit` is never used;
 * each save reports through `onChanged` (the modal host refreshes its grid and
 * keeps the record open).
 */
export function RegistryDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: registry,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(registryDetailQueryKey(id), () => fetchRegistry(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('registries.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !registry) {
    return <DetailLoading />
  }

  return <RegistryDetailView registry={registry} onChanged={onChanged} />
}

/** Create only (spec 0200): there is no edit form, and no host asks for one. */
export function RegistryFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: RegistryDetail) => {
    queryClient.invalidateQueries({ queryKey: registryDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  return <GuardedRegistryForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: RegistryDetailScreen,
  FormScreen: RegistryFormScreen,
}
