import { useTranslation } from 'react-i18next'
import { Database } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCard, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { SourceDetailWithPermissions } from '@/features/sources/types'

interface SourceDetailViewProps {
  source: SourceDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single source, rendered as an enterprise-CRM record
 * (Opportunita' reference kit): the identity card on the left, the Attivita'
 * tab on the right when authorized, a metadata footer.
 */
export function SourceDetailView({ source, onEdit }: SourceDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = source.permissions.resource.update
  const createdAt = formatDateTime(source.created_at)

  const tabs: RecordCollaborationTab[] = source.permissions.actions.view_activity
    ? [activityLogTab('sources', source.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={source.name} icon={<Database />} />}
            title={source.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('sources.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
