/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchContractStatus, contractStatusDetailQueryKey } from '@/features/contract-statuses/api'
import { ContractStatusForm } from '@/features/contract-statuses/contract-status-form'
import { ContractStatusDetailView } from '@/features/contract-statuses/contract-status-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { ContractStatusDetail } from '@/features/contract-statuses/types'

/**
 * Content-only `contract-statuses` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function ContractStatusDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: contractStatus,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(contractStatusDetailQueryKey(id), () => fetchContractStatus(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('contractStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !contractStatus) {
    return <DetailLoading />
  }

  return <ContractStatusDetailView contractStatus={contractStatus} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a record being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function ContractStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('contractStatuses.form.leaveConfirm.title'),
    description: t('contractStatuses.form.leaveConfirm.description'),
    confirmLabel: t('contractStatuses.form.leaveConfirm.confirm'),
    cancelLabel: t('contractStatuses.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: ContractStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: contractStatusDetailQueryKey(saved.id) })
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
      <ContractStatusForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'contract-statuses',
  basePath: '/contract-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.contractStatuses',
  DetailScreen: ContractStatusDetailScreen,
  FormScreen: ContractStatusFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
