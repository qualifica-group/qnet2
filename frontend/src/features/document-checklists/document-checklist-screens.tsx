/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchDocumentChecklist } from '@/features/document-checklists/api'
import { DocumentChecklistDetailView } from '@/features/document-checklists/document-checklist-detail'
import { DocumentChecklistForm } from '@/features/document-checklists/document-checklist-form'
import { documentChecklistKeys } from '@/features/document-checklists/query-keys'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleScreens } from '@/features/modules/types'

/**
 * Detail screen: the form in edit mode. `skeletonOnRefetch: false` keeps the
 * form mounted (and its unsaved edits) when a save refreshes the record.
 */
export function DocumentChecklistDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, error, refetch } = useEntityDetail(
    documentChecklistKeys.detail(id),
    () => fetchDocumentChecklist(id),
    true,
    { skeletonOnRefetch: false },
  )

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('documentChecklists.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !data) {
    return <DetailLoading />
  }

  return <DocumentChecklistDetailView checklist={data} onChanged={onChanged} />
}

/**
 * Create only: the registry generates no `:id/edit` route (the detail is the
 * edit surface). Leaving a checklist being created always asks first.
 */
export function DocumentChecklistFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const leaveGuard = useFormLeaveGuard({
    title: t('documentChecklists.form.leaveConfirm.title'),
    description: t('documentChecklists.form.leaveConfirm.description'),
    confirmLabel: t('documentChecklists.form.leaveConfirm.confirm'),
    cancelLabel: t('documentChecklists.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
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
      <DocumentChecklistForm
        onSuccess={(saved) => {
          // Saved: the navigation to the new record's detail is no "leaving".
          leaveGuard.allowLeave()
          onSuccess(saved.id)
        }}
        onCancel={handleCancel}
      />
    </>
  )
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: DocumentChecklistDetailScreen,
  FormScreen: DocumentChecklistFormScreen,
}
