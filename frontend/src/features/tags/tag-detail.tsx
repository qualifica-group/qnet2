import { useTranslation } from 'react-i18next'
import { Tag as TagIcon } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCard, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { TagDetailWithPermissions } from '@/features/tags/types'

interface TagDetailViewProps {
  tag: TagDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single tag, rendered as an enterprise-CRM record
 * (Opportunita' reference layout): the identity card on the left, the
 * activity card on the right, a metadata footer. A tag has no field beyond
 * its name, so the record card carries no `RecordSection`.
 */
export function TagDetailView({ tag, onEdit }: TagDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = tag.permissions.resource.update
  const createdAt = formatDateTime(tag.created_at)
  const canViewActivity = tag.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard tabs={[activityLogTab('tags', tag.id, t('activityLog.title'))]} />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={
              <DetailMonogram name={tag.name} icon={<TagIcon />} className="size-10 text-base [&>svg]:size-5" />
            }
            title={tag.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('tags.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
