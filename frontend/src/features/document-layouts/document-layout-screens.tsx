/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { documentLayoutDetailQueryKey, fetchDocumentLayout } from '@/features/document-layouts/api'
import { DocumentLayoutForm } from '@/features/document-layouts/document-layout-form'
import { DocumentLayoutDetailView } from '@/features/document-layouts/document-layout-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { DocumentLayoutDetail } from '@/features/document-layouts/types'

/**
 * Content-only `document-layouts` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`. The module opens as a page (`OPEN_MODE_PAGE`, spec
 * 0069 D-9): the visual editor needs the width of a canvas + inspector +
 * variables panel, it does not fit a modal Sheet.
 */
export function DocumentLayoutDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: documentLayout,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(documentLayoutDetailQueryKey(id), () => fetchDocumentLayout(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('documentLayouts.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !documentLayout) {
    return <DetailLoading />
  }

  return <DocumentLayoutDetailView documentLayout={documentLayout} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a layout being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function DocumentLayoutFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('documentLayouts.form.leaveConfirm.title'),
    description: t('documentLayouts.form.leaveConfirm.description'),
    confirmLabel: t('documentLayouts.form.leaveConfirm.confirm'),
    cancelLabel: t('documentLayouts.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: DocumentLayoutDetail) => {
    queryClient.invalidateQueries({ queryKey: documentLayoutDetailQueryKey(saved.id) })
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
      <DocumentLayoutForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'document-layouts',
  basePath: '/document-layouts',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.documentLayouts',
  DetailScreen: DocumentLayoutDetailScreen,
  FormScreen: DocumentLayoutFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
