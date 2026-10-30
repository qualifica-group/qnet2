/* eslint-disable react-refresh/only-export-components */
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { parseEntityId } from '@/routes/entity-id'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import { commissionConfigurationDetailKey, fetchCommissionConfiguration } from './api'
import { CommissionConfigurationForm } from './commission-configuration-form'
import { CommissionConfigurationDetailView } from './commission-configuration-detail'
import {
  COMMISSION_CREATE_REFERENT_PARAM,
  COMMISSION_CREATE_REGISTRY_PARAM,
  type CommissionConfigurationDetail,
  type CommissionRecipientPreset,
} from './types'

/** The detail edits its rows in place, so `onEdit` is never used; each save reports through `onChanged`. */
export function CommissionConfigurationDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, error, refetch } = useEntityDetail(
    commissionConfigurationDetailKey(id),
    () => fetchCommissionConfiguration(id),
  )
  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('commissionConfigurations.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }
  if (isLoading || !data) return <DetailLoading />
  return <CommissionConfigurationDetailView configuration={data} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a configuration being created (Cancel, the
 * Sheet's X/overlay/Esc, a link, a reload) always asks first.
 */
export function CommissionConfigurationFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('commissionConfigurations.form.leaveConfirm.title'),
    description: t('commissionConfigurations.form.leaveConfirm.description'),
    confirmLabel: t('commissionConfigurations.form.leaveConfirm.confirm'),
    cancelLabel: t('commissionConfigurations.form.leaveConfirm.cancel'),
    tone: 'warning',
  })
  const params = mode.type === 'create' ? mode.params : undefined
  const registryId = parseEntityId(String(params?.[COMMISSION_CREATE_REGISTRY_PARAM] ?? ''))
  const referentId = parseEntityId(String(params?.[COMMISSION_CREATE_REFERENT_PARAM] ?? ''))
  // Stable: the form rebuilds its defaults whenever the preset identity changes.
  const recipientPreset = useMemo<CommissionRecipientPreset | null>(() => {
    if (registryId != null) return { role: 'SUPPLIER', recipientId: registryId }
    if (referentId != null) return { role: 'REPORTER', recipientId: referentId }
    return null
  }, [registryId, referentId])

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: CommissionConfigurationDetail) => {
    queryClient.invalidateQueries({ queryKey: commissionConfigurationDetailKey(saved.id) })
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
      <CommissionConfigurationForm
        recipientPreset={recipientPreset}
        onSuccess={handleSuccess}
        onCancel={handleCancel}
      />
    </>
  )
}

export const moduleScreen: ModuleRegistryEntry = {
  domain: 'commission-configurations',
  basePath: '/commission-configurations',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.commissionConfigurations',
  DetailScreen: CommissionConfigurationDetailScreen,
  FormScreen: CommissionConfigurationFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
