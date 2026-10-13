/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchReferentType, referentTypeDetailQueryKey } from '@/features/referent-types/api'
import { ReferentTypeForm } from '@/features/referent-types/referent-type-form'
import { ReferentTypeDetailView } from '@/features/referent-types/referent-type-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { ReferentTypeDetail } from '@/features/referent-types/types'

/**
 * Content-only `referent-types` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function ReferentTypeDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: referentType,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(referentTypeDetailQueryKey(id), () => fetchReferentType(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('referentTypes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !referentType) {
    return <DetailLoading />
  }

  return <ReferentTypeDetailView referentType={referentType} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a referent type being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function ReferentTypeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('referentTypes.form.leaveConfirm.title'),
    description: t('referentTypes.form.leaveConfirm.description'),
    confirmLabel: t('referentTypes.form.leaveConfirm.confirm'),
    cancelLabel: t('referentTypes.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: ReferentTypeDetail) => {
    queryClient.invalidateQueries({ queryKey: referentTypeDetailQueryKey(saved.id) })
    // Saved: the navigation to the new record's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = () => {
    void leaveGuard.confirmLeave().then((leave) => {
      if (leave) {
        onCancel()
      }
    })
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <ReferentTypeForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'referent-types',
  basePath: '/referent-types',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.referentTypes',
  DetailScreen: ReferentTypeDetailScreen,
  FormScreen: ReferentTypeFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
