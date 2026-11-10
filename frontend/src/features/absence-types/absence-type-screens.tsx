/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { absenceTypeKeys, fetchAbsenceType } from '@/features/absence-types/api'
import { AbsenceTypeForm } from '@/features/absence-types/absence-type-form'
import { AbsenceTypeDetailView } from '@/features/absence-types/absence-type-detail'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleScreens } from '@/features/modules/types'
import { type AbsenceType } from '@/features/absence-types/types'

/** Content-only `absence-types` screens for the module registry (spec 0042). */
export function AbsenceTypeDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: absenceType,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(absenceTypeKeys.detail(id), () => fetchAbsenceType(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('absenceTypes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !absenceType) {
    return <DetailLoading />
  }

  return <AbsenceTypeDetailView absenceType={absenceType} onChanged={onChanged} />
}

/** Create only: there is no edit form, and the registry generates no `:id/edit` route. */
export function AbsenceTypeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: AbsenceType) => {
    void queryClient.invalidateQueries({ queryKey: absenceTypeKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  return <AbsenceTypeForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: AbsenceTypeDetailScreen,
  FormScreen: AbsenceTypeFormScreen,
}
