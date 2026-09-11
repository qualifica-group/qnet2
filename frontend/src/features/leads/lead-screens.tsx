/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchLead, leadDetailQueryKey } from '@/features/leads/api'
import { LeadForm } from '@/features/leads/lead-form'
import { LeadDetailView } from '@/features/leads/lead-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { LeadDetail } from '@/features/leads/types'

/**
 * Content-only `leads` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `LeadsTable`'s inline loaders, which the rewire removed.
 */
export function LeadDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: lead,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(leadDetailQueryKey(id), () => fetchLead(id))

  if (isError) {
    return (
      <DetailError
        message={t('leads.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !lead) {
    return <DetailLoading />
  }

  return <LeadDetailView lead={lead} onEdit={onEdit} />
}

export function LeadFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: LeadDetail) => {
    queryClient.invalidateQueries({ queryKey: leadDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <LeadForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <LeadEditScreen leadId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface LeadEditScreenProps {
  leadId: number
  onSuccess: (lead: LeadDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized lead detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function LeadEditScreen({ leadId, onSuccess, onCancel }: LeadEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: lead,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(leadDetailQueryKey(leadId), () => fetchLead(leadId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('leads.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !lead) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <LeadForm mode={{ type: 'edit', lead }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'leads',
  basePath: '/leads',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.leads',
  DetailScreen: LeadDetailScreen,
  FormScreen: LeadFormScreen,
  // The record card renders its own Edit action AND the lead -> opportunity
  // CTA, so the generic page header must not stack a second button — the same
  // registration Opportunita' and Utenti carry. `DetailPageActions` is gone
  // with it: a page-only extra left the Sheet without the conversion at all.
  detailOwnsEditAction: true,
}