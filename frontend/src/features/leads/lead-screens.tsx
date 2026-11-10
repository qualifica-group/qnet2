/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchLead, leadDetailQueryKey } from '@/features/leads/api'
import { LeadForm } from '@/features/leads/lead-form'
import { LeadDetailView } from '@/features/leads/lead-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleScreens,
} from '@/features/modules/types'
import type { LeadDetail } from '@/features/leads/types'

/**
 * Content-only `leads` screens for the module registry (spec 0042): fetch +
 * the presentational view/form, no page chrome. Reused as-is by the modal
 * Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`).
 *
 * Spec 0217: the detail edits its fields in place, so `onEdit` is never used;
 * each save reports through `onChanged` (the modal host refreshes its grid and
 * keeps the record open).
 */
export function LeadDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: lead,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(leadDetailQueryKey(id), () => fetchLead(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('leads.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !lead) {
    return <DetailLoading />
  }

  return <LeadDetailView lead={lead} onChanged={onChanged} />
}

/** Create only (spec 0217): there is no edit form, and the registry generates no `:id/edit` route. */
export function LeadFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  // Leaving a lead being created — Cancel, the Sheet's X/overlay/Esc, a link,
  // a reload — always asks first (spec 0195 D-9 applied to Lead).
  const leaveGuard = useFormLeaveGuard({
    title: t('leads.form.leaveConfirm.title'),
    description: t('leads.form.leaveConfirm.description'),
    confirmLabel: t('leads.form.leaveConfirm.confirm'),
    cancelLabel: t('leads.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: LeadDetail) => {
    queryClient.invalidateQueries({ queryKey: leadDetailQueryKey(saved.id) })
    // Saved: the navigation to the new lead's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = async () => {
    if (await leaveGuard.confirmLeave()) {
      onCancel()
    }
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <LeadForm onSuccess={handleSuccess} onCancel={() => void handleCancel()} />
    </>
  )
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: LeadDetailScreen,
  FormScreen: LeadFormScreen,
}
