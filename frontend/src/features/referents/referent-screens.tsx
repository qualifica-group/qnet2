/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchReferent, referentDetailQueryKey } from '@/features/referents/api'
import { ReferentForm } from '@/features/referents/referent-form'
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
 */
export function ReferentDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: referent,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(referentDetailQueryKey(id), () => fetchReferent(id))

  if (isError) {
    return (
      <DetailError
        message={t('referents.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !referent) {
    return <DetailLoading />
  }

  return <ReferentDetailView referent={referent} onEdit={onEdit} />
}

export function ReferentFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: ReferentDetail) => {
    queryClient.invalidateQueries({ queryKey: referentDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <ReferentForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <ReferentEditScreen referentId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface ReferentEditScreenProps {
  referentId: number
  onSuccess: (referent: ReferentDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized referent detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function ReferentEditScreen({ referentId, onSuccess, onCancel }: ReferentEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: referent,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(referentDetailQueryKey(referentId), () => fetchReferent(referentId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('referents.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !referent) {
    return <RecordFormSkeleton />
  }

  return <ReferentForm mode={{ type: 'edit', referent }} onSuccess={onSuccess} onCancel={onCancel} />
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
  // The record card renders its own Edit action, and the form its own identity
  // bar — same registration Opportunità carries, so the hosts must not stack a
  // second heading or button on top of them.
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}