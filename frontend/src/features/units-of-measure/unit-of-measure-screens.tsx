/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchUnitOfMeasure, unitOfMeasureDetailQueryKey } from '@/features/units-of-measure/api'
import { UnitOfMeasureForm } from '@/features/units-of-measure/unit-of-measure-form'
import { UnitOfMeasureDetailView } from '@/features/units-of-measure/unit-of-measure-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { UnitOfMeasureDetail } from '@/features/units-of-measure/types'

/**
 * Content-only `units-of-measure` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`.
 */
export function UnitOfMeasureDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: unitOfMeasure,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(unitOfMeasureDetailQueryKey(id), () => fetchUnitOfMeasure(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('unitsOfMeasure.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !unitOfMeasure) {
    return <DetailLoading />
  }

  return <UnitOfMeasureDetailView unitOfMeasure={unitOfMeasure} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a unit of measure being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function UnitOfMeasureFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('unitsOfMeasure.form.leaveConfirm.title'),
    description: t('unitsOfMeasure.form.leaveConfirm.description'),
    confirmLabel: t('unitsOfMeasure.form.leaveConfirm.confirm'),
    cancelLabel: t('unitsOfMeasure.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: UnitOfMeasureDetail) => {
    queryClient.invalidateQueries({ queryKey: unitOfMeasureDetailQueryKey(saved.id) })
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
      <UnitOfMeasureForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
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
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
