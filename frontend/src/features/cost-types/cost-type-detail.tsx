import { useTranslation } from 'react-i18next'
import { Tags } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { COST_TYPES_DOMAIN, type CostTypeDetailWithPermissions } from '@/features/cost-types/types'

interface CostTypeDetailViewProps {
  costType: CostTypeDetailWithPermissions
  /** Opens the module's edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/** Read-only record view of a cost type: identity card, activity card, metadata footer. */
export function CostTypeDetailView({ costType, onEdit }: CostTypeDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(costType.created_at)
  const updatedAt = formatDateTime(costType.updated_at)
  const canEdit = costType.permissions.resource.update
  const canViewActivity = costType.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab(COST_TYPES_DOMAIN, costType.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={costType.name} icon={<Tags />} />}
            title={costType.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('costTypes.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('costTypes.detail.description')}>
                  {costType.description ? costType.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('costTypes.detail.sortOrder')}>{costType.sort_order}</RecordField>
                <RecordField label={t('costTypes.detail.status')}>
                  {costType.is_active ? t('costTypes.detail.active') : t('costTypes.detail.inactive')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('costTypes.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('costTypes.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
