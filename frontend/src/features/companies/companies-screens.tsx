/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCompany } from '@/features/companies/api'
import { CompanyForm } from '@/features/companies/company-form'
import { CompanyDetailView } from '@/features/companies/company-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { CompanyDetail } from '@/features/companies/types'

/** Same detail query key `CompanyDetailView`/the former `EditCompanyLoader` used. */
function detailQueryKey(id: number) {
  return ['companies', 'detail', id] as const
}

/**
 * Content-only `companies` screens for the module registry (spec 0042):
 * `CompanyDetailView` already fetches its own detail, so the detail screen is
 * a direct passthrough. Reused as-is by the modal Sheet (`useModuleOpener`)
 * and by the generic dedicated pages (`ModuleDetailPage`/`ModuleFormPage`),
 * which own the surrounding chrome.
 */
export function CompanyDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  return <CompanyDetailView companyId={id} onEdit={onEdit} />
}

export function CompanyFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (company: CompanyDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(company.id) })
    onSuccess(company.id)
  }

  if (mode.type === 'create') {
    return <CompanyForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <CompanyEditLoader companyId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface CompanyEditLoaderProps {
  companyId: number
  onSuccess: (company: CompanyDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized company detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot (moved here unchanged from the former `companies-table.tsx`
 * inline loader).
 */
function CompanyEditLoader({ companyId, onSuccess, onCancel }: CompanyEditLoaderProps) {
  const { t } = useTranslation()
  const {
    data: company,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(companyId), () => fetchCompany(companyId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('companies.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !company) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <CompanyForm mode={{ type: 'edit', company }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'companies',
  basePath: '/companies',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.companies',
  DetailScreen: CompanyDetailScreen,
  FormScreen: CompanyFormScreen,
  // The record card renders its own Edit action and the form its own identity
  // bar, so the generic hosts must not stack a second heading or button — the
  // same registration Opportunita', Utenti and Lead carry.
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}