/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchVatRate } from '@/features/vat-rates/api'
import { VatRateForm } from '@/features/vat-rates/vat-rate-form'
import { VatRateDetailView } from '@/features/vat-rates/vat-rate-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { VatRateDetail } from '@/features/vat-rates/types'

/** Query key for a single VAT rate's detail (fresh-on-open pattern), moved verbatim from `VatRatesTable`. */
function detailQueryKey(id: number) {
  return ['vat-rates', 'detail', id] as const
}

/**
 * Content-only `vat-rates` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `VatRatesTable`'s inline loaders, which the rewire removed.
 */
export function VatRateDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: vatRate,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchVatRate(id))

  if (isError) {
    return (
      <DetailError
        message={t('vatRates.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !vatRate) {
    return <DetailLoading />
  }

  return <VatRateDetailView vatRate={vatRate} onEdit={onEdit} />
}

export function VatRateFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: VatRateDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <VatRateForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <VatRateEditScreen vatRateId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface VatRateEditScreenProps {
  vatRateId: number
  onSuccess: (vatRate: VatRateDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized VAT rate detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function VatRateEditScreen({ vatRateId, onSuccess, onCancel }: VatRateEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: vatRate,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(vatRateId), () => fetchVatRate(vatRateId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('vatRates.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !vatRate) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <VatRateForm mode={{ type: 'edit', vatRate }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'vat-rates',
  basePath: '/vat-rates',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.vatRates',
  DetailScreen: VatRateDetailScreen,
  FormScreen: VatRateFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}