/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { absenceTypeKeys, fetchAbsenceType } from '@/features/absence-types/api'
import { AbsenceTypeForm } from '@/features/absence-types/absence-type-form'
import { AbsenceTypeDetailView } from '@/features/absence-types/absence-type-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import { ABSENCE_TYPES_DOMAIN, type AbsenceType } from '@/features/absence-types/types'

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

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: ABSENCE_TYPES_DOMAIN,
  basePath: `/${ABSENCE_TYPES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.absenceTypes',
  DetailScreen: AbsenceTypeDetailScreen,
  FormScreen: AbsenceTypeFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}
