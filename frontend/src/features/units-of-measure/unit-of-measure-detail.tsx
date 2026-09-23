import { useTranslation } from 'react-i18next'
import { Ruler } from 'lucide-react'
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
import type { UnitOfMeasureDetailWithPermissions } from '@/features/units-of-measure/types'

interface UnitOfMeasureDetailViewProps {
  unitOfMeasure: UnitOfMeasureDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single unit of measure, rendered as an
 * enterprise-CRM record on the same kit Opportunita' uses: the identity/
 * fields card on the left, the activity card on the right, a metadata
 * footer. `code` is shown as the identity band subtitle, not a labeled field.
 */
export function UnitOfMeasureDetailView({ unitOfMeasure, onEdit }: UnitOfMeasureDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(unitOfMeasure.created_at)
  const updatedAt = formatDateTime(unitOfMeasure.updated_at)
  const canEdit = unitOfMeasure.permissions.resource.update
  const canViewActivity = unitOfMeasure.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('units-of-measure', unitOfMeasure.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={unitOfMeasure.name} icon={<Ruler />} />}
            title={unitOfMeasure.name}
            subtitle={unitOfMeasure.code}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('unitsOfMeasure.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('unitsOfMeasure.detail.symbol')}>{unitOfMeasure.symbol}</RecordField>
                <RecordField label={t('unitsOfMeasure.detail.description')}>
                  {unitOfMeasure.description ? unitOfMeasure.description : <DetailEmpty />}
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
              <span className="font-medium">{t('unitsOfMeasure.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('unitsOfMeasure.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
