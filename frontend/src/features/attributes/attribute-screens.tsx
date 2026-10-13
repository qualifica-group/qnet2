/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { attributeDetailQueryKey, fetchAttribute } from '@/features/attributes/api'
import { AttributeForm } from '@/features/attributes/attribute-form'
import { AttributeDetailView } from '@/features/attributes/attribute-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { AttributeDetail } from '@/features/attributes/types'

/**
 * Content-only `attributes` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`).
 *
 * Spec 0240: the detail edits its rows in place, so `onEdit` is never used;
 * each save reports through `onChanged`.
 */
export function AttributeDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: attribute,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(attributeDetailQueryKey(id), () => fetchAttribute(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('attributes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !attribute) {
    return <DetailLoading />
  }

  return <AttributeDetailView attribute={attribute} onChanged={onChanged} />
}

/**
 * Create and duplicate only (spec 0240): there is no edit form, and the
 * registry generates no `:id/edit` route. Leaving an attribute being created
 * — Cancel, the Sheet's X/overlay/Esc, a link, a reload — always asks first.
 */
export function AttributeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('attributes.form.leaveConfirm.title'),
    description: t('attributes.form.leaveConfirm.description'),
    confirmLabel: t('attributes.form.leaveConfirm.confirm'),
    cancelLabel: t('attributes.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type === 'edit') {
    return null
  }

  const handleSuccess = (saved: AttributeDetail) => {
    queryClient.invalidateQueries({ queryKey: attributeDetailQueryKey(saved.id) })
    // Saved: the navigation to the new attribute's detail is no "leaving".
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
      {mode.type === 'duplicate' ? (
        <AttributeDuplicateLoader attributeId={mode.id} onSuccess={handleSuccess} onCancel={handleCancel} />
      ) : (
        <AttributeForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={handleCancel} />
      )}
    </>
  )
}

interface AttributeDuplicateLoaderProps {
  attributeId: number
  onSuccess: (attribute: AttributeDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized source attribute before mounting the create
 * form pre-filled from it (row action "duplicate"), so the copy starts from
 * authoritative values rather than a stale snapshot. The form itself still
 * submits via the create path.
 */
function AttributeDuplicateLoader({ attributeId, onSuccess, onCancel }: AttributeDuplicateLoaderProps) {
  const { t } = useTranslation()
  const {
    data: attribute,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(attributeDetailQueryKey(attributeId), () => fetchAttribute(attributeId))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('attributes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !attribute) {
    return <RecordFormSkeleton />
  }

  return <AttributeForm mode={{ type: 'duplicate', source: attribute }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'attributes',
  basePath: '/attributes',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.attributes',
  DetailScreen: AttributeDetailScreen,
  FormScreen: AttributeFormScreen,
  // The detail IS the edit form (spec 0240): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}