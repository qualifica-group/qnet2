import { useTranslation } from 'react-i18next'
import { Tag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCard, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { ReferentTypeDetailWithPermissions } from '@/features/referent-types/types'

interface ReferentTypeDetailViewProps {
  referentType: ReferentTypeDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single referent type, rendered as an enterprise-CRM
 * record on the same kit Opportunita' uses: the identity card on the left,
 * the activity card on the right, a metadata footer. A referent type carries
 * no field beyond its name, so the card has no sections grid.
 */
export function ReferentTypeDetailView({ referentType, onEdit }: ReferentTypeDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(referentType.created_at)
  const canEdit = referentType.permissions.resource.update
  const canViewActivity = referentType.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('referent-types', referentType.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={referentType.name} icon={<Tag />} />}
            title={referentType.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('referentTypes.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
