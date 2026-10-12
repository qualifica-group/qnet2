/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchReferent, referentDetailQueryKey } from '@/features/referents/api'
import { GuardedReferentForm } from '@/features/referents/guarded-referent-form'
import { ReferentDetailView } from '@/features/referents/referent-detail'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { ReferentDetail } from '@/features/referents/types'

/**
 * Content-only `referents` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Unlike the
 * modal-native modules, `referents` defaults to its bespoke dedicated pages
 * (spec 0022, `ReferentDetailPage`/`ReferentFormPage`) and does NOT get
 * generated routes (`generateRoutes: false`) — these screens only back the
 * 'modal' alternative a user can opt into (spec 0042).
 *
 * Spec 0216: the detail edits its fields in place, so `onEdit` is never used;
 * each save reports through `onChanged` (the modal host refreshes its grid and
 * keeps the record open).
 */
export function ReferentDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: referent,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(referentDetailQueryKey(id), () => fetchReferent(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('referents.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !referent) {
    return <DetailLoading />
  }

  return <ReferentDetailView referent={referent} onChanged={onChanged} />
}

/** Create only (spec 0216): there is no edit form, and no host asks for one. */
export function ReferentFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: ReferentDetail) => {
    queryClient.invalidateQueries({ queryKey: referentDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  return <GuardedReferentForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'referents',
  basePath: '/referents',
  defaultMode: OPEN_MODE_PAGE,
  generateRoutes: false,
  labelKey: 'navigation.referents',
  DetailScreen: ReferentDetailScreen,
  FormScreen: ReferentFormScreen,
  // The detail IS the edit form (spec 0216): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
