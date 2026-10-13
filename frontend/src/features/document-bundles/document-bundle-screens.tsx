/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { documentBundleDetailQueryKey, fetchDocumentBundle } from '@/features/document-bundles/api'
import { DocumentBundleForm } from '@/features/document-bundles/document-bundle-form'
import { DocumentBundleDetailView } from '@/features/document-bundles/document-bundle-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { DocumentBundle } from '@/features/document-bundles/types'

/**
 * Content-only `document-bundles` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`.
 */
export function DocumentBundleDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: documentBundle,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(documentBundleDetailQueryKey(id), () => fetchDocumentBundle(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('documentBundles.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !documentBundle) {
    return <DetailLoading />
  }

  return <DocumentBundleDetailView documentBundle={documentBundle} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a bundle being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function DocumentBundleFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('documentBundles.form.leaveConfirm.title'),
    description: t('documentBundles.form.leaveConfirm.description'),
    confirmLabel: t('documentBundles.form.leaveConfirm.confirm'),
    cancelLabel: t('documentBundles.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: DocumentBundle) => {
    queryClient.invalidateQueries({ queryKey: documentBundleDetailQueryKey(saved.id) })
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
      <DocumentBundleForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'document-bundles',
  basePath: '/document-bundles',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.documentBundles',
  DetailScreen: DocumentBundleDetailScreen,
  FormScreen: DocumentBundleFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
