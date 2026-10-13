/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
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

/**
 * Content-only `agent-fiscal-profiles` screens for the module registry (spec
 * 0042): reused by the modal Sheet (`useModuleOpener`) and the generic
 * dedicated pages.
 */
export function AgentFiscalProfileDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
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

  return <AgentFiscalProfileDetailView profile={profile} onEdit={onEdit} />
}

export function AgentFiscalProfileFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: AgentFiscalProfileDetail) => {
    void queryClient.invalidateQueries({ queryKey: agentFiscalProfileKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <AgentFiscalProfileForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <AgentFiscalProfileEditScreen profileId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface AgentFiscalProfileEditScreenProps {
  profileId: number
  onSuccess: (profile: AgentFiscalProfileDetail) => void
  onCancel: () => void
}

/** Fetches the fresh detail before mounting the edit form so the PUT starts from authoritative values. */
function AgentFiscalProfileEditScreen({ profileId, onSuccess, onCancel }: AgentFiscalProfileEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: profile,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(agentFiscalProfileKeys.detail(profileId), () => fetchAgentFiscalProfile(profileId))

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
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <AgentFiscalProfileForm mode={{ type: 'edit', profile }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: AGENT_FISCAL_PROFILES_DOMAIN,
  basePath: `/${AGENT_FISCAL_PROFILES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.agentFiscalProfiles',
  DetailScreen: AgentFiscalProfileDetailScreen,
  FormScreen: AgentFiscalProfileFormScreen,
  detailOwnsEditAction: true,
}
