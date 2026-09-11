import { useTranslation } from 'react-i18next'
import { History } from 'lucide-react'
import {
  RecordCanvas,
  RecordCard,
  RecordMeta,
  RecordSection,
} from '@/components/detail/record-panel'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import { cn } from '@/lib/utils'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
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
  const canViewActivity = lead.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, canViewActivity && RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <LeadDetailHeader lead={lead} onEdit={onEdit} />
            <LeadDetailStats lead={lead} />
            <LeadDetailSections lead={lead} />
          </RecordCard>
        </div>

        {canViewActivity ? (
          <div className={RECORD_COLUMN_CLASS}>
            <RecordCard className="p-4">
              <RecordSection title={t('activityLog.title')} icon={<History />}>
                <ActivityLogSection resource="leads" id={lead.id} />
              </RecordSection>
            </RecordCard>
          </div>
        ) : null}
      </div>

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
