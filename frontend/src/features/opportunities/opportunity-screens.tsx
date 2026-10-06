/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import {
  fetchOpportunity,
  opportunityDetailQueryKey,
} from '@/features/opportunities/api'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import { OpportunityDetailView } from '@/features/opportunities/opportunity-detail'
import { useOpportunityCreateMode } from '@/features/opportunities/use-opportunity-create-mode'
import { parseEntityId } from '@/routes/entity-id'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { OpportunityCreateFormMode, OpportunityDetail } from '@/features/opportunities/types'

/**
 * Content-only `opportunities` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`).
 *
 * Spec 0198: the detail edits its fields in place, so `onEdit` is never
 * used; each save reports through `onChanged` (the modal host refreshes its
 * grid and keeps the record open).
 */
export function OpportunityDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
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

  return <OpportunityDetailView opportunity={opportunity} onChanged={onChanged} />
}

/**
 * Create only (spec 0198): the registry generates no `:id/edit` route. Reads
 * `lead_id` from `mode.params` (spec 0045), the single channel a `FormScreen`
 * gets its create-time context through regardless of where it is mounted:
 * the modal Sheet hands the params straight through, while `ModuleFormPage`
 * converts the deep-link's `?lead_id=N` query string into the same `params`
 * shape before mounting this screen. `lead_id` can therefore arrive as either
 * a `number` (modal caller) or a `string` (parsed query string) — normalize
 * to string before `parseEntityId`.
 */
export function OpportunityFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: OpportunityDetail) => {
    queryClient.invalidateQueries({ queryKey: opportunityDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  const leadId = parseEntityId(String(mode.params?.lead_id ?? ''))
  // Spec 0199: "Nuova opportunita'" from the anagrafica detail's tab.
  const registryId = parseEntityId(String(mode.params?.registry_id ?? ''))
  return (
    <OpportunityCreateScreen leadId={leadId} registryId={registryId} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface OpportunityCreateScreenProps {
  leadId: number | null
  /** Ignored when converting a lead: the lead's own anagrafica wins (BR-1). */
  registryId: number | null
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/** Resolves the `?lead_id=N` create-from-lead context (D-2 short-circuit included) before mounting the form. */
function OpportunityCreateScreen({ leadId, registryId, onSuccess, onCancel }: OpportunityCreateScreenProps) {
  const { t } = useTranslation()
  const createMode = useOpportunityCreateMode(leadId)

  if (createMode.status === 'loading') {
    return <RecordFormSkeleton />
  }

  if (createMode.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('opportunities.form.defaultsLoadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={createMode.retry}>
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

  const mode = leadId === null && registryId !== null ? { ...createMode.mode, registryId } : createMode.mode
  return <GuardedOpportunityForm mode={mode} onSuccess={onSuccess} onCancel={onCancel} />
}

interface GuardedOpportunityFormProps {
  mode: OpportunityCreateFormMode
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/**
 * The create form behind its leave guard: Cancel, the Sheet's X/overlay/Esc,
 * a link, a reload — leaving always asks first (spec 0195 D-9 applied to
 * Opportunita'). Mounted only once there is a form to lose.
 */
function GuardedOpportunityForm({ mode, onSuccess, onCancel }: GuardedOpportunityFormProps) {
  const { t } = useTranslation()
  const leaveGuard = useFormLeaveGuard({
    title: t('opportunities.form.leaveConfirm.title'),
    description: t('opportunities.form.leaveConfirm.description'),
    confirmLabel: t('opportunities.form.leaveConfirm.confirm'),
    cancelLabel: t('opportunities.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  const handleSuccess = (saved: OpportunityDetail) => {
    // Saved: the navigation to the new opportunity's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved)
  }

  const handleCancel = async () => {
    if (await leaveGuard.confirmLeave()) {
      onCancel()
    }
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <OpportunityForm mode={mode} onSuccess={handleSuccess} onCancel={() => void handleCancel()} />
    </>
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
  // The detail IS the edit form (spec 0198): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
