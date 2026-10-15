import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { IdCard } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import { AgentFiscalProfileSections } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-rows'
import type {
  AgentFiscalProfileFormValues,
  AgentFiscalProfileSchemaOutput,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-schema'
import { useAgentFiscalProfileForm } from '@/features/agent-fiscal-profiles/use-agent-fiscal-profile-form'
import {
  AGENT_FISCAL_PROFILES_DOMAIN,
  type AgentFiscalProfileDetailWithPermissions,
  type AgentFiscalProfileFormMode,
  type AgentFiscalProfileRefs,
} from '@/features/agent-fiscal-profiles/types'

interface AgentFiscalProfileDetailViewProps {
  profile: AgentFiscalProfileDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of an agent fiscal profile on the record kit (spec 0264 D-4): there
 * is no edit page, every row edits IN PLACE, one at a time, each save a PATCH
 * of that row's field(s) alone.
 */
export function AgentFiscalProfileDetailView(props: AgentFiscalProfileDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.profile.permissions}>
      <AgentFiscalProfileDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function AgentFiscalProfileDetailContent({ profile, onChanged }: AgentFiscalProfileDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<AgentFiscalProfileFormMode>(() => ({ type: 'edit', profile }), [profile])
  const { form, onSubmit, serverError, clearServerError } = useAgentFiscalProfileForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<AgentFiscalProfileFormValues, AgentFiscalProfileSchemaOutput>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('agentFiscalProfiles.form.genericError'),
  })
  const refs = useMemo<AgentFiscalProfileRefs>(
    () => ({
      referent: profile.referent,
      vatRate: profile.vat_rate
        ? { id: profile.vat_rate.id, name: `${profile.vat_rate.name} (${Number(profile.vat_rate.rate)}%)` }
        : null,
    }),
    [profile],
  )
  const createdAt = formatDateTime(profile.created_at)
  const updatedAt = formatDateTime(profile.updated_at)
  const until = profile.valid_until ? formatDate(profile.valid_until) : t('agentFiscalProfiles.detail.open')

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={
          profile.permissions.actions.view_activity
            ? [activityLogTab(AGENT_FISCAL_PROFILES_DOMAIN, profile.id, t('activityLog.title'))]
            : []
        }
        header={
          <RecordCardHeader
            media={<DetailMonogram name={profile.referent.name} icon={<IdCard />} />}
            title={profile.referent.name}
            subtitle={`${formatDate(profile.valid_from)} - ${until}`}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <AgentFiscalProfileSections inline={inline} canEdit={profile.permissions.resource.update} refs={refs} />
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('agentFiscalProfiles.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('agentFiscalProfiles.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
