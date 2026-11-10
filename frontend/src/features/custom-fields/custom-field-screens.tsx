/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { customFieldDetailQueryKey, fetchCustomFieldDefinition } from '@/features/custom-fields/api'
import { CustomFieldDefinitionForm } from '@/features/custom-fields/custom-field-definition-form'
import { CustomFieldDetailView } from '@/features/custom-fields/custom-field-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleScreens,
} from '@/features/modules/types'
import type { CustomFieldDefinitionDetail } from '@/features/custom-fields/types'

/**
 * Content-only `custom-fields` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. Reused as-is by the
 * modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`).
 *
 * Spec 0231: the detail edits its rows in place, so `onEdit` is never used;
 * each save reports through `onChanged`.
 */
export function CustomFieldDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: definition,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(customFieldDetailQueryKey(id), () => fetchCustomFieldDefinition(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('customFields.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !definition) {
    return <DetailLoading />
  }

  return <CustomFieldDetailView definition={definition} onChanged={onChanged} />
}

/**
 * Create only (spec 0231): there is no edit form, and the registry generates
 * no `:id/edit` route. Leaving a definition being created — Cancel, the
 * Sheet's X/overlay/Esc, a link, a reload — always asks first.
 */
export function CustomFieldFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('customFields.form.leaveConfirm.title'),
    description: t('customFields.form.leaveConfirm.description'),
    confirmLabel: t('customFields.form.leaveConfirm.confirm'),
    cancelLabel: t('customFields.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: CustomFieldDefinitionDetail) => {
    queryClient.invalidateQueries({ queryKey: customFieldDetailQueryKey(saved.id) })
    // Saved: the navigation to the new definition's detail is no "leaving".
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
      <CustomFieldDefinitionForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: CustomFieldDetailScreen,
  FormScreen: CustomFieldFormScreen,
}
