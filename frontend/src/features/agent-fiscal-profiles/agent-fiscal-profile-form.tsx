import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useAgentFiscalProfileFormMeta } from '@/features/agent-fiscal-profiles/use-agent-fiscal-profile-form-meta'
import { AgentFiscalProfileFormBody } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-form-body'
import type { AgentFiscalProfileDetail, AgentFiscalProfileFormMode } from '@/features/agent-fiscal-profiles/types'

interface AgentFiscalProfileFormProps {
  mode: AgentFiscalProfileFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (profile: AgentFiscalProfileDetail) => void
  onCancel: () => void
}

/**
 * Create/edit form of an agent fiscal profile. Resolves the resource
 * `ResourcePermissions` first (edit: from the loaded detail; create:
 * `GET /meta/agent-fiscal-profiles`), then hands off to the body.
 */
export function AgentFiscalProfileForm(props: AgentFiscalProfileFormProps) {
  const { t } = useTranslation()
  const meta = useAgentFiscalProfileFormMeta(props.mode)

  if (meta.status === 'loading') {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <AgentFiscalProfileFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
