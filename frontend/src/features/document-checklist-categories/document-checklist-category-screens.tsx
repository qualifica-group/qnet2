/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchDocumentChecklistCategory } from '@/features/document-checklist-categories/api'
import { DocumentChecklistCategoryDetailView } from '@/features/document-checklist-categories/document-checklist-category-detail'
import { DocumentChecklistCategoryForm } from '@/features/document-checklist-categories/document-checklist-category-form'
import { documentChecklistCategoryKeys } from '@/features/document-checklist-categories/query-keys'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleScreens } from '@/features/modules/types'

/** Detail screen: the form in edit mode; `skeletonOnRefetch: false` keeps unsaved edits across a save refresh. */
export function DocumentChecklistCategoryDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, error, refetch } = useEntityDetail(
    documentChecklistCategoryKeys.detail(id),
    () => fetchDocumentChecklistCategory(id),
    true,
    { skeletonOnRefetch: false },
  )

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('documentChecklistCategories.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !data) {
    return <DetailLoading />
  }

  return <DocumentChecklistCategoryDetailView category={data} onChanged={onChanged} />
}

/** Create only: the detail is the edit surface. Leaving a category being created always asks first. */
export function DocumentChecklistCategoryFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const leaveGuard = useFormLeaveGuard({
    title: t('documentChecklistCategories.form.leaveConfirm.title'),
    description: t('documentChecklistCategories.form.leaveConfirm.description'),
    confirmLabel: t('documentChecklistCategories.form.leaveConfirm.confirm'),
    cancelLabel: t('documentChecklistCategories.form.leaveConfirm.cancel'),
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
      <DocumentChecklistCategoryForm
        onSuccess={(saved) => {
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
  DetailScreen: DocumentChecklistCategoryDetailScreen,
  FormScreen: DocumentChecklistCategoryFormScreen,
}
