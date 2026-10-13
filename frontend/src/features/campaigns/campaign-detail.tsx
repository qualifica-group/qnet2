import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CampaignDetailHeader, CampaignDetailStats } from '@/features/campaigns/campaign-detail-header'
import { CampaignDetailSections } from '@/features/campaigns/campaign-detail-sections'
import { useCampaignInlineEdit } from '@/features/campaigns/use-campaign-inline-edit'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { CampaignDetailWithPermissions as CampaignDetailData } from '@/features/campaigns/types'

interface CampaignDetailViewProps {
  campaign: CampaignDetailData
  /** Called after an in-place save, so the host refreshes whatever lists the campaign (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single campaign, rendered as an enterprise-CRM record on the
 * same kit every detail uses: identity/KPI/sections card, the activity in the
 * side column, a metadata footer. Container-query driven (`RecordCanvas`) so
 * the same tree renders inside a resizable Sheet and on `/campaigns/:id`.
 *
 * There is no edit page (spec 0221): the sections' fields edit IN PLACE, one
 * at a time (`RecordInlineField`, driven by `useCampaignInlineEdit`), each save
 * a PATCH of that field (and of the cascade a Project pick prefills).
 */
export function CampaignDetailView(props: CampaignDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.campaign.permissions}>
      <CampaignDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function CampaignDetailContent({ campaign, onChanged }: CampaignDetailViewProps) {
  const { t } = useTranslation()
  const editor = useCampaignInlineEdit(campaign, onChanged)
  const createdAt = formatDateTime(campaign.created_at)
  const collaborationTabs = campaign.permissions.actions.view_activity
    ? [activityLogTab('campaigns', campaign.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <CampaignDetailHeader campaign={campaign} />
            <CampaignDetailStats campaign={campaign} />
          </>
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...editor.form}>
          <CampaignDetailSections campaign={campaign} editor={editor} />
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('campaigns.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
