import { useTranslation } from 'react-i18next'
import { CreditCard } from 'lucide-react'
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
import type { PaymentMethodDetailWithPermissions } from '@/features/payment-methods/types'

interface PaymentMethodDetailViewProps {
  paymentMethod: PaymentMethodDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single payment method, rendered as an enterprise-CRM
 * record on the same kit Opportunita' uses: the identity/fields card on the
 * left, the activity card on the right, a metadata footer. `code` is shown as
 * the identity band subtitle (mirrors `UnitOfMeasureDetailView`), not a
 * labeled field.
 */
export function PaymentMethodDetailView({ paymentMethod, onEdit }: PaymentMethodDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(paymentMethod.created_at)
  const updatedAt = formatDateTime(paymentMethod.updated_at)
  const canEdit = paymentMethod.permissions.resource.update
  const canViewActivity = paymentMethod.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('payment-methods', paymentMethod.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={paymentMethod.name} icon={<CreditCard />} />}
            title={paymentMethod.name}
            subtitle={paymentMethod.code}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('paymentMethods.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('paymentMethods.detail.payment_method_code')}>
                  {paymentMethod.payment_method_code ? paymentMethod.payment_method_code : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('paymentMethods.detail.description')}>
                  {paymentMethod.description ? paymentMethod.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('paymentMethods.detail.payment_instructions')}>
                  {paymentMethod.payment_instructions ? paymentMethod.payment_instructions : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('paymentMethods.detail.payment_days')}>
                  {paymentMethod.payment_days !== null ? paymentMethod.payment_days : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('paymentMethods.detail.sort_order')}>
                  {paymentMethod.sort_order}
                </RecordField>
                <RecordField label={t('paymentMethods.detail.is_active')}>
                  {paymentMethod.is_active ? t('common.yes') : t('common.no')}
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
              <span className="font-medium">{t('paymentMethods.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('paymentMethods.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
