/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchVatRate, vatRateDetailQueryKey } from '@/features/vat-rates/api'
import { VatRateForm } from '@/features/vat-rates/vat-rate-form'
import { VatRateDetailView } from '@/features/vat-rates/vat-rate-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { VatRateDetail } from '@/features/vat-rates/types'

/**
 * Content-only `vat-rates` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`.
 */
export function VatRateDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: vatRate,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(vatRateDetailQueryKey(id), () => fetchVatRate(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('vatRates.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !vatRate) {
    return <DetailLoading />
  }

  return <VatRateDetailView vatRate={vatRate} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a VAT rate being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function VatRateFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('vatRates.form.leaveConfirm.title'),
    description: t('vatRates.form.leaveConfirm.description'),
    confirmLabel: t('vatRates.form.leaveConfirm.confirm'),
    cancelLabel: t('vatRates.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: VatRateDetail) => {
    queryClient.invalidateQueries({ queryKey: vatRateDetailQueryKey(saved.id) })
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
      <VatRateForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'vat-rates',
  basePath: '/vat-rates',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.vatRates',
  DetailScreen: VatRateDetailScreen,
  FormScreen: VatRateFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
