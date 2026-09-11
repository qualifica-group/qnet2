/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCompanySite } from '@/features/company-sites/api'
import { CompanySiteForm } from '@/features/company-sites/company-site-form'
import { CompanySiteDetailView } from '@/features/company-sites/company-site-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { CompanySiteDetail } from '@/features/company-sites/types'

/** Same detail query key `CompanySiteDetailView`/the former `EditCompanySiteLoader` used. */
function detailQueryKey(id: number) {
  return ['company-sites', 'detail', id] as const
}

/**
 * Content-only `company-sites` screens for the module registry (spec 0042):
 * `CompanySiteDetailView` already fetches its own detail, so the detail
 * screen is a direct passthrough. Reused as-is by the modal Sheet
 * (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`), which own the surrounding chrome.
 *
 * `onDefaultChange`/`onSiteChange` (the former table's live grid refresh on
 * "set default"/logo change while the Sheet stayed open) have no slot in the
 * registry's generic `ModuleDetailScreenProps`/`ModuleFormScreenProps` (both
 * only carry `id`), so they are not wired here — flagged to the module
 * registry owner, not invented.
 */
export function CompanySiteDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  return <CompanySiteDetailView companySiteId={id} onEdit={onEdit} />
}

export function CompanySiteFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (companySite: CompanySiteDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(companySite.id) })
    onSuccess(companySite.id)
  }

  if (mode.type === 'create') {
    return (
      <CompanySiteForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
    )
  }

  return (
    <CompanySiteEditLoader companySiteId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface CompanySiteEditLoaderProps {
  companySiteId: number
  onSuccess: (companySite: CompanySiteDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized company-site detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot (moved here unchanged from the former
 * `company-sites-table.tsx` inline loader).
 */
function CompanySiteEditLoader({ companySiteId, onSuccess, onCancel }: CompanySiteEditLoaderProps) {
  const { t } = useTranslation()
  const {
    data: companySite,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(companySiteId), () => fetchCompanySite(companySiteId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('companySites.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !companySite) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <CompanySiteForm
      mode={{ type: 'edit', companySite }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
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
  // The record card renders its own Edit action and the form its own identity
  // bar, so the generic hosts must not stack a second heading or button — the
  // same registration Opportunita', Utenti and Lead carry.
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}