import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { LeadDetailHeader, LeadStatsStrip } from '@/features/leads/lead-detail-header'
import { LeadDetailSections } from '@/features/leads/lead-detail-sections'
import { useLeadInlineEdit } from '@/features/leads/use-lead-inline-edit'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { LeadDetailWithPermissions as LeadDetailData } from '@/features/leads/types'

interface LeadDetailViewProps {
  lead: LeadDetailData
  /** Called after an in-place save, so the host refreshes whatever lists the lead (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single lead, rendered as an enterprise-CRM record on the same
 * kit every detail uses: the identity/KPI/sections card, the activity in the
 * side column, a metadata footer. Container-query driven (`RecordCanvas`) so
 * the same tree renders inside a resizable Sheet and on `/leads/:id`.
 *
 * There is no edit page (spec 0217): the sections' fields edit IN PLACE, one
 * at a time (`RecordInlineField`, driven by `useLeadInlineEdit`), each save a
 * PATCH of that field alone — plus what its cascade wrote.
 *
 * A Lead has no name/code of its own (D-3, spec 0041 D-1): the anagrafica is
 * the identity, the campaign the subtitle.
 */
export function LeadDetailView(props: LeadDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.lead.permissions}>
      <LeadDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function LeadDetailContent({ lead, onChanged }: LeadDetailViewProps) {
  const { t } = useTranslation()
  const editor = useLeadInlineEdit(lead, onChanged)
  const createdAt = formatDateTime(lead.created_at)
  const updatedAt = formatDateTime(lead.updated_at)
  const collaborationTabs = lead.permissions.actions.view_activity
    ? [activityLogTab('leads', lead.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <LeadDetailHeader lead={lead} />
            <LeadStatsStrip
              operator={lead.operator?.name ?? null}
              site={lead.operational_site?.label ?? null}
              source={lead.source?.name ?? null}
              productCount={lead.products_of_interest?.length ?? 0}
            />
          </>
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...editor.form}>
          <LeadDetailSections lead={lead} editor={editor} />
        </Form>
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
