/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchOperationalSite, operationalSiteDetailQueryKey } from '@/features/operational-sites/api'
import { OperationalSiteForm } from '@/features/operational-sites/operational-site-form'
import { OperationalSiteDetailView } from '@/features/operational-sites/operational-site-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { OperationalSiteDetail } from '@/features/operational-sites/types'

/**
 * Content-only `operational-sites` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`.
 */
export function OperationalSiteDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: operationalSite,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(operationalSiteDetailQueryKey(id), () => fetchOperationalSite(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('operationalSites.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !operationalSite) {
    return <DetailLoading />
  }

  return <OperationalSiteDetailView operationalSite={operationalSite} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a site being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function OperationalSiteFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('operationalSites.form.leaveConfirm.title'),
    description: t('operationalSites.form.leaveConfirm.description'),
    confirmLabel: t('operationalSites.form.leaveConfirm.confirm'),
    cancelLabel: t('operationalSites.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (operationalSite: OperationalSiteDetail) => {
    queryClient.invalidateQueries({ queryKey: operationalSiteDetailQueryKey(operationalSite.id) })
    // Saved: the navigation to the new site's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(operationalSite.id)
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
      <OperationalSiteForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'operational-sites',
  basePath: '/operational-sites',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.operationalSites',
  DetailScreen: OperationalSiteDetailScreen,
  FormScreen: OperationalSiteFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
