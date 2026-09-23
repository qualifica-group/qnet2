import { useTranslation } from 'react-i18next'
import { ListTree } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCard, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { SectorDetailWithPermissions } from '@/features/sectors/types'

interface SectorDetailViewProps {
  sector: SectorDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single sector, rendered as an enterprise-CRM record
 * (Opportunita' reference layout): the identity card on the left, the
 * activity card on the right, a metadata footer. A sector has no field
 * beyond its name and its parent (already the header subtitle), so the
 * record card carries no `RecordSection`.
 */
export function SectorDetailView({ sector, onEdit }: SectorDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = sector.permissions.resource.update
  const createdAt = formatDateTime(sector.created_at)
  const canViewActivity = sector.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard tabs={[activityLogTab('sectors', sector.id, t('activityLog.title'))]} />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={
              <DetailMonogram
                name={sector.name}
                icon={<ListTree />}
                className="size-10 text-base [&>svg]:size-5"
              />
            }
            title={sector.name}
            subtitle={sector.parent?.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('sectors.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
