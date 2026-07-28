/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import {
  fetchOpportunity,
  opportunityDetailQueryKey,
} from '@/features/opportunities/api'
import { OpportunityForm, OpportunityFormSkeleton } from '@/features/opportunities/opportunity-form'
import { OpportunityDetailView } from '@/features/opportunities/opportunity-detail'
import { useOpportunityCreateMode } from '@/features/opportunities/use-opportunity-create-mode'
import { parseEntityId } from '@/routes/entity-id'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { OpportunityDetail } from '@/features/opportunities/types'

/**
 * Content-only `opportunities` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `OpportunitiesTable`'s inline loaders, which the rewire removed.
 */
export function OpportunityDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: opportunity,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(opportunityDetailQueryKey(id), () => fetchOpportunity(id))

  if (isError) {
    return (
      <DetailError
        message={t('opportunities.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !opportunity) {
    return <DetailLoading />
  }

  return <OpportunityDetailView opportunity={opportunity} onEdit={onEdit} />
}

/**
 * Create branch reads `lead_id` from `mode.params` (spec 0045), the single
 * channel a `FormScreen` gets its create-time context through regardless of
 * where it is mounted: the modal Sheet hands the params straight through,
 * while `ModuleFormPage` converts the deep-link's `?lead_id=N` query string
 * into the same `params` shape before mounting this screen. `lead_id` can
 * therefore arrive as either a `number` (modal caller) or a `string` (parsed
 * query string) — normalize to string before `parseEntityId`.
 */
export function OpportunityFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: OpportunityDetail) => {
    queryClient.invalidateQueries({ queryKey: opportunityDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'edit') {
    return (
      <OpportunityEditScreen opportunityId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
    )
  }

  const leadId =
    mode.type === 'create' ? parseEntityId(String(mode.params?.lead_id ?? '')) : null
  return <OpportunityCreateScreen leadId={leadId} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface OpportunityCreateScreenProps {
  leadId: number | null
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/** Resolves the `?lead_id=N` create-from-lead context (D-2 short-circuit included) before mounting the form. */
function OpportunityCreateScreen({ leadId, onSuccess, onCancel }: OpportunityCreateScreenProps) {
  const { t } = useTranslation()
  const createMode = useOpportunityCreateMode(leadId)

  if (createMode.status === 'loading') {
    return <OpportunityFormSkeleton />
  }

  if (createMode.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('opportunities.form.defaultsLoadError')}
        </p>
        <Button variant="outline" size="sm" onClick={createMode.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (createMode.status === 'existing') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm font-medium">{t('opportunities.form.existingOpportunityTitle')}</p>
        <p className="text-sm text-muted-foreground">
          {t('opportunities.form.existingOpportunityDescription')}
        </p>
        <Button asChild>
          <Link to={`/opportunities/${createMode.existingOpportunityId}`}>
            {t('opportunities.form.goToExistingOpportunity')}
          </Link>
        </Button>
      </div>
    )
  }

  return <OpportunityForm mode={createMode.mode} onSuccess={onSuccess} onCancel={onCancel} />
}

interface OpportunityEditScreenProps {
  opportunityId: number
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized opportunity detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function OpportunityEditScreen({ opportunityId, onSuccess, onCancel }: OpportunityEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: opportunity,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(opportunityDetailQueryKey(opportunityId), () => fetchOpportunity(opportunityId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('opportunities.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !opportunity) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <OpportunityForm mode={{ type: 'edit', opportunity }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'opportunities',
  basePath: '/opportunities',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.opportunities',
  DetailScreen: OpportunityDetailScreen,
  FormScreen: OpportunityFormScreen,
  detailOwnsEditAction: true,
}