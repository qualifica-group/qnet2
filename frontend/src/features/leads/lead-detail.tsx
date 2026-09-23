import { useTranslation } from 'react-i18next'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { LeadDetailHeader, LeadDetailStats } from '@/features/leads/lead-detail-header'
import { LeadDetailSections } from '@/features/leads/lead-detail-sections'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { LeadDetailWithPermissions as LeadDetailData } from '@/features/leads/types'

interface LeadDetailViewProps {
  lead: LeadDetailData
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single lead, rendered as an enterprise-CRM record on
 * the same kit Opportunita' and Utenti use: the identity/KPI/sections card on
 * the left, the activity card on the right, a metadata footer.
 * Container-query driven (`RecordCanvas`) so the same tree renders correctly
 * both inside a resizable Sheet and on the full-bleed `/leads/:id` page.
 *
 * A Lead has no name/code of its own (D-3, spec 0041 D-1): the anagrafica is
 * the identity, the campaign the subtitle. It also owns almost no data of its
 * own — which is why the record leads with the records it points at (see
 * `LeadDetailSections`) rather than with a field list.
 */
export function LeadDetailView({ lead, onEdit }: LeadDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(lead.created_at)
  const updatedAt = formatDateTime(lead.updated_at)
  const collaborationTabs = lead.permissions.actions.view_activity
    ? [activityLogTab('leads', lead.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <LeadDetailHeader lead={lead} onEdit={onEdit} />
          <LeadDetailStats lead={lead} />
          <LeadDetailSections lead={lead} />
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('leads.columns.createdAt')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('leads.detail.updatedAt')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
