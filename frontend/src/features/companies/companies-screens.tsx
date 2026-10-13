/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCompany, companyDetailQueryKey } from '@/features/companies/api'
import { CompanyForm } from '@/features/companies/company-form'
import { CompanyDetailView } from '@/features/companies/company-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { CompanyDetail } from '@/features/companies/types'

/**
 * Content-only `companies` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`.
 */
export function CompanyDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: company,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(companyDetailQueryKey(id), () => fetchCompany(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('companies.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !company) {
    return <DetailLoading />
  }

  return <CompanyDetailView company={company} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a company being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function CompanyFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('companies.form.leaveConfirm.title'),
    description: t('companies.form.leaveConfirm.description'),
    confirmLabel: t('companies.form.leaveConfirm.confirm'),
    cancelLabel: t('companies.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (company: CompanyDetail) => {
    queryClient.invalidateQueries({ queryKey: companyDetailQueryKey(company.id) })
    // Saved: the navigation to the new company's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(company.id)
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
      <CompanyForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'companies',
  basePath: '/companies',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.companies',
  DetailScreen: CompanyDetailScreen,
  FormScreen: CompanyFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
