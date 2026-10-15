/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { agentFiscalProfileKeys, fetchAgentFiscalProfile } from '@/features/agent-fiscal-profiles/api'
import { AgentFiscalProfileForm } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-form'
import { AgentFiscalProfileDetailView } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import { AGENT_FISCAL_PROFILES_DOMAIN, type AgentFiscalProfileDetail } from '@/features/agent-fiscal-profiles/types'

/** The detail edits its rows in place (spec 0264), so `onEdit` is never used; each save reports through `onChanged`. */
export function AgentFiscalProfileDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: profile,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(agentFiscalProfileKeys.detail(id), () => fetchAgentFiscalProfile(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('agentFiscalProfiles.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !profile) {
    return <DetailLoading />
  }

  return <AgentFiscalProfileDetailView profile={profile} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a profile being created always asks first.
 */
export function AgentFiscalProfileFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('agentFiscalProfiles.form.leaveConfirm.title'),
    description: t('agentFiscalProfiles.form.leaveConfirm.description'),
    confirmLabel: t('agentFiscalProfiles.form.leaveConfirm.confirm'),
    cancelLabel: t('agentFiscalProfiles.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: AgentFiscalProfileDetail) => {
    void queryClient.invalidateQueries({ queryKey: agentFiscalProfileKeys.detail(saved.id) })
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
      <AgentFiscalProfileForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: AGENT_FISCAL_PROFILES_DOMAIN,
  basePath: `/${AGENT_FISCAL_PROFILES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.agentFiscalProfiles',
  DetailScreen: AgentFiscalProfileDetailScreen,
  FormScreen: AgentFiscalProfileFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band, so the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
