/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { companyClosureKeys, fetchCompanyClosure } from '@/features/company-closures/api'
import { CompanyClosureDetailView } from '@/features/company-closures/company-closure-detail'
import { CompanyClosureFormBody } from '@/features/company-closures/company-closure-form-body'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleScreens } from '@/features/modules/types'

/** Content-only `company-closures` screens for the module registry (spec 0042). */
export function CompanyClosureDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: closure,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(companyClosureKeys.detail(id), () => fetchCompanyClosure(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('companyClosures.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !closure) {
    return <DetailLoading />
  }

  return <CompanyClosureDetailView closure={closure} onEdit={onEdit} />
}

/** Create, and edit through the detail's Edit button (a PUT of the whole closure: the rules couple its fields). */
export function CompanyClosureFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editId = mode.type === 'edit' ? mode.id : null
  const detail = useEntityDetail(
    companyClosureKeys.detail(editId ?? 0),
    () => fetchCompanyClosure(editId ?? 0),
    editId !== null,
  )

  if (mode.type === 'duplicate') {
    return null
  }

  const handleDone = (saved: { id: number }) => {
    void queryClient.invalidateQueries({ queryKey: companyClosureKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  if (editId === null) {
    return <CompanyClosureFormBody onDone={handleDone} onCancel={onCancel} />
  }

  if (detail.isError) {
    return (
      <DetailError
        error={detail.error}
        message={t('companyClosures.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => detail.refetch()}
      />
    )
  }

  if (detail.isLoading || !detail.data) {
    return <RecordFormSkeleton />
  }

  return <CompanyClosureFormBody closure={detail.data} onDone={handleDone} onCancel={onCancel} />
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: CompanyClosureDetailScreen,
  FormScreen: CompanyClosureFormScreen,
}
