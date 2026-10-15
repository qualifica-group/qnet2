/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { enasarcoParameterKeys, fetchEnasarcoParameter } from '@/features/enasarco-parameters/api'
import { EnasarcoParameterForm } from '@/features/enasarco-parameters/enasarco-parameter-form'
import { EnasarcoParameterDetailView } from '@/features/enasarco-parameters/enasarco-parameter-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import { ENASARCO_PARAMETERS_DOMAIN, type EnasarcoParameterDetail } from '@/features/enasarco-parameters/types'

/** The detail edits its rows in place (spec 0264), so `onEdit` is never used; each save reports through `onChanged`. */
export function EnasarcoParameterDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: parameter,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(enasarcoParameterKeys.detail(id), () => fetchEnasarcoParameter(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('enasarcoParameters.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !parameter) {
    return <DetailLoading />
  }

  return <EnasarcoParameterDetailView parameter={parameter} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a parameter set being created always asks first.
 */
export function EnasarcoParameterFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('enasarcoParameters.form.leaveConfirm.title'),
    description: t('enasarcoParameters.form.leaveConfirm.description'),
    confirmLabel: t('enasarcoParameters.form.leaveConfirm.confirm'),
    cancelLabel: t('enasarcoParameters.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: EnasarcoParameterDetail) => {
    void queryClient.invalidateQueries({ queryKey: enasarcoParameterKeys.detail(saved.id) })
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
      <EnasarcoParameterForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: ENASARCO_PARAMETERS_DOMAIN,
  basePath: `/${ENASARCO_PARAMETERS_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.enasarcoParameters',
  DetailScreen: EnasarcoParameterDetailScreen,
  FormScreen: EnasarcoParameterFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band, so the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
