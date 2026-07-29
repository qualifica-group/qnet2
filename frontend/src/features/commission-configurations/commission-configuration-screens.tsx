/* eslint-disable react-refresh/only-export-components */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import {
  commissionConfigurationDetailKey,
  fetchCommissionConfiguration,
} from './api'
import { CommissionConfigurationForm } from './commission-configuration-form'
import { CommissionConfigurationDetailView } from './commission-configuration-detail'
import type { CommissionConfigurationDetail } from './types'

export function CommissionConfigurationDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, refetch } = useEntityDetail(
    commissionConfigurationDetailKey(id),
    () => fetchCommissionConfiguration(id),
  )
  if (isError) return <DetailError message={t('commissionConfigurations.detail.loadError')} retryLabel={t('common.retry')} onRetry={() => refetch()} />
  if (isLoading || !data) return <DetailLoading />
  return <CommissionConfigurationDetailView configuration={data} />
}

function EditLoader({
  id,
  onSuccess,
  onCancel,
}: {
  id: number
  onSuccess: (value: CommissionConfigurationDetail) => void
  onCancel: () => void
}) {
  const { t } = useTranslation()
  const { data, isLoading, isError, refetch } = useEntityDetail(
    commissionConfigurationDetailKey(id),
    () => fetchCommissionConfiguration(id),
  )
  if (isError) return <div className="p-4"><p role="alert" className="mb-3 text-sm text-destructive">{t('commissionConfigurations.detail.loadError')}</p><Button variant="outline" onClick={() => refetch()}>{t('common.retry')}</Button></div>
  if (isLoading || !data) return <div className="grid gap-4 p-4">{Array.from({ length: 8 }, (_, index) => <Skeleton key={index} className="h-9" />)}</div>
  return <CommissionConfigurationForm mode={{ type: 'edit', configuration: data }} onSuccess={onSuccess} onCancel={onCancel} />
}

export function CommissionConfigurationFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()
  const saved = (configuration: CommissionConfigurationDetail) => {
    queryClient.invalidateQueries({ queryKey: commissionConfigurationDetailKey(configuration.id) })
    onSuccess(configuration.id)
  }
  return mode.type === 'create'
    ? <CommissionConfigurationForm mode={{ type: 'create' }} onSuccess={saved} onCancel={onCancel} />
    : <EditLoader id={mode.id} onSuccess={saved} onCancel={onCancel} />
}

export const moduleScreen: ModuleRegistryEntry = {
  domain: 'commission-configurations',
  basePath: '/commission-configurations',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.commissionConfigurations',
  DetailScreen: CommissionConfigurationDetailScreen,
  FormScreen: CommissionConfigurationFormScreen,
}
