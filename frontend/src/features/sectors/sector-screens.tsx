/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchSector } from '@/features/sectors/api'
import { sectorKeys } from '@/features/sectors/query-keys'
import { SectorForm } from '@/features/sectors/sector-form'
import { SectorDetailView } from '@/features/sectors/sector-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { SectorDetail } from '@/features/sectors/types'

/**
 * Content-only `sectors` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function SectorDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: sector,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(sectorKeys.detail(id), () => fetchSector(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('sectors.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !sector) {
    return <DetailLoading />
  }

  return <SectorDetailView sector={sector} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a sector being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function SectorFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('sectors.form.leaveConfirm.title'),
    description: t('sectors.form.leaveConfirm.description'),
    confirmLabel: t('sectors.form.leaveConfirm.confirm'),
    cancelLabel: t('sectors.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: SectorDetail) => {
    queryClient.invalidateQueries({ queryKey: sectorKeys.detail(saved.id) })
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
      <SectorForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'sectors',
  basePath: '/sectors',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.sectors',
  DetailScreen: SectorDetailScreen,
  FormScreen: SectorFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
