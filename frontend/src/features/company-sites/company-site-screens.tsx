/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCompanySite, companySiteDetailQueryKey } from '@/features/company-sites/api'
import { CompanySiteForm } from '@/features/company-sites/company-site-form'
import { CompanySiteDetailView } from '@/features/company-sites/company-site-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { CompanySiteDetail } from '@/features/company-sites/types'

/**
 * Content-only `company-sites` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`.
 */
export function CompanySiteDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: companySite,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(companySiteDetailQueryKey(id), () => fetchCompanySite(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('companySites.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !companySite) {
    return <DetailLoading />
  }

  return <CompanySiteDetailView companySite={companySite} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a company site being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function CompanySiteFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('companySites.form.leaveConfirm.title'),
    description: t('companySites.form.leaveConfirm.description'),
    confirmLabel: t('companySites.form.leaveConfirm.confirm'),
    cancelLabel: t('companySites.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (companySite: CompanySiteDetail) => {
    queryClient.invalidateQueries({ queryKey: companySiteDetailQueryKey(companySite.id) })
    // Saved: the navigation to the new site's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(companySite.id)
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
      <CompanySiteForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'company-sites',
  basePath: '/company-sites',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.companySites',
  DetailScreen: CompanySiteDetailScreen,
  FormScreen: CompanySiteFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
