/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import {
  fetchProformaRequest,
  proformaRequestDetailQueryKey,
  PROFORMA_REQUESTS_DOMAIN,
} from '@/features/proforma-requests/api'
import { ProformaRequestDetailView } from '@/features/proforma-requests/proforma-request-detail'
import { ProformaRequestEditScreen } from '@/features/proforma-requests/proforma-request-edit-screen'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'

/** Content-only detail screen for the module registry (spec 0042). */
export function ProformaRequestDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, refetch } = useEntityDetail(proformaRequestDetailQueryKey(id), () =>
    fetchProformaRequest(id),
  )

  if (isError) {
    return (
      <DetailError
        message={t('proformaRequests.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }
  if (isLoading || !data) {
    return <DetailLoading />
  }
  return <ProformaRequestDetailView proformaRequest={data} onEdit={onEdit} />
}

/** Edit-only form screen: a request is never created from the module, only from a work order. */
export function ProformaRequestFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  if (mode.type !== 'edit') {
    return null
  }
  return <ProformaRequestEditScreen id={mode.id} onSuccess={() => onSuccess(mode.id)} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: PROFORMA_REQUESTS_DOMAIN,
  basePath: '/proforma-requests',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.proformaRequests',
  DetailScreen: ProformaRequestDetailScreen,
  FormScreen: ProformaRequestFormScreen,
  detailOwnsEditAction: true,
}
