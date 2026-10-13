/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchBusinessFunction, businessFunctionDetailQueryKey } from '@/features/business-functions/api'
import { BusinessFunctionForm } from '@/features/business-functions/business-function-form'
import { BusinessFunctionDetailView } from '@/features/business-functions/business-function-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { BusinessFunctionDetail } from '@/features/business-functions/types'

/**
 * Content-only `business-functions` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function BusinessFunctionDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: businessFunction,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(businessFunctionDetailQueryKey(id), () => fetchBusinessFunction(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('businessFunctions.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !businessFunction) {
    return <DetailLoading />
  }

  return <BusinessFunctionDetailView businessFunction={businessFunction} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a business function being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function BusinessFunctionFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('businessFunctions.form.leaveConfirm.title'),
    description: t('businessFunctions.form.leaveConfirm.description'),
    confirmLabel: t('businessFunctions.form.leaveConfirm.confirm'),
    cancelLabel: t('businessFunctions.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: BusinessFunctionDetail) => {
    queryClient.invalidateQueries({ queryKey: businessFunctionDetailQueryKey(saved.id) })
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
      <BusinessFunctionForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'business-functions',
  basePath: '/business-functions',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.businessFunctions',
  DetailScreen: BusinessFunctionDetailScreen,
  FormScreen: BusinessFunctionFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
