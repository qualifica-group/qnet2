/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchInvoiceTag, invoiceTagDetailQueryKey } from '@/features/invoice-tags/api'
import { InvoiceTagForm } from '@/features/invoice-tags/invoice-tag-form'
import { InvoiceTagDetailView } from '@/features/invoice-tags/invoice-tag-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { InvoiceTagDetail } from '@/features/invoice-tags/types'

/**
 * Content-only `invoice-tags` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function InvoiceTagDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: invoiceTag,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(invoiceTagDetailQueryKey(id), () => fetchInvoiceTag(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('invoiceTags.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !invoiceTag) {
    return <DetailLoading />
  }

  return <InvoiceTagDetailView invoiceTag={invoiceTag} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a priority being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function InvoiceTagFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('invoiceTags.form.leaveConfirm.title'),
    description: t('invoiceTags.form.leaveConfirm.description'),
    confirmLabel: t('invoiceTags.form.leaveConfirm.confirm'),
    cancelLabel: t('invoiceTags.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: InvoiceTagDetail) => {
    queryClient.invalidateQueries({ queryKey: invoiceTagDetailQueryKey(saved.id) })
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
      <InvoiceTagForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'invoice-tags',
  basePath: '/invoice-tags',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.invoiceTags',
  DetailScreen: InvoiceTagDetailScreen,
  FormScreen: InvoiceTagFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
