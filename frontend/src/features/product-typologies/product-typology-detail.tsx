import { useTranslation } from 'react-i18next'
import { Shapes } from 'lucide-react'
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
import type { ProductTypologyDetailWithPermissions } from '@/features/product-typologies/types'

interface ProductTypologyDetailViewProps {
  productTypology: ProductTypologyDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single product typology, rendered as an
 * enterprise-CRM record (Opportunita' reference layout): the
 * identity/fields card on the left, the activity card on the right, a
 * metadata footer. `code` is shown as the header subtitle, not a labeled
 * field.
 */
export function ProductTypologyDetailView({ productTypology, onEdit }: ProductTypologyDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = productTypology.permissions.resource.update
  const createdAt = formatDateTime(productTypology.created_at)
  const updatedAt = formatDateTime(productTypology.updated_at)
  const canViewActivity = productTypology.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('product-typologies', productTypology.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={
              <DetailMonogram
                name={productTypology.name}
                icon={<Shapes />}
                className="size-10 text-base [&>svg]:size-5"
              />
            }
            title={productTypology.name}
            subtitle={productTypology.code}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('productTypologies.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('productTypologies.detail.description')}>
                  {productTypology.description ? productTypology.description : <DetailEmpty />}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('productTypologies.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('productTypologies.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
