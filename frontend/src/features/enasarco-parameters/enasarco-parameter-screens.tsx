/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { enasarcoParameterKeys, fetchEnasarcoParameter } from '@/features/enasarco-parameters/api'
import { EnasarcoParameterForm } from '@/features/enasarco-parameters/enasarco-parameter-form'
import { EnasarcoParameterDetailView } from '@/features/enasarco-parameters/enasarco-parameter-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import { ENASARCO_PARAMETERS_DOMAIN, type EnasarcoParameterDetail } from '@/features/enasarco-parameters/types'

/**
 * Content-only `enasarco-parameters` screens for the module registry (spec
 * 0042): reused by the modal Sheet (`useModuleOpener`) and the generic
 * dedicated pages.
 */
export function EnasarcoParameterDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: parameter,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(enasarcoParameterKeys.detail(id), () => fetchEnasarcoParameter(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('enasarcoParameters.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !parameter) {
    return <DetailLoading />
  }

  return <EnasarcoParameterDetailView parameter={parameter} onEdit={onEdit} />
}

export function EnasarcoParameterFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: EnasarcoParameterDetail) => {
    void queryClient.invalidateQueries({ queryKey: enasarcoParameterKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <EnasarcoParameterForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <EnasarcoParameterEditScreen parameterId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface EnasarcoParameterEditScreenProps {
  parameterId: number
  onSuccess: (parameter: EnasarcoParameterDetail) => void
  onCancel: () => void
}

/** Fetches the fresh detail before mounting the edit form so the PUT starts from authoritative values. */
function EnasarcoParameterEditScreen({ parameterId, onSuccess, onCancel }: EnasarcoParameterEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: parameter,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(enasarcoParameterKeys.detail(parameterId), () => fetchEnasarcoParameter(parameterId))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('enasarcoParameters.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !parameter) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <EnasarcoParameterForm mode={{ type: 'edit', parameter }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: ENASARCO_PARAMETERS_DOMAIN,
  basePath: `/${ENASARCO_PARAMETERS_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.enasarcoParameters',
  DetailScreen: EnasarcoParameterDetailScreen,
  FormScreen: EnasarcoParameterFormScreen,
  detailOwnsEditAction: true,
}
