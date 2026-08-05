import { useTranslation } from 'react-i18next'
import { CreditCard, History } from 'lucide-react'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { PaymentMethodDetailWithPermissions } from '@/features/payment-methods/types'

interface PaymentMethodDetailViewProps {
  paymentMethod: PaymentMethodDetailWithPermissions
}

/**
 * Read-only detail of a single payment method. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `RewardStatusDetailView`). `code` is shown as the hero subtitle
 * (mirrors `AttributeDetailView`), not a labeled field.
 */
export function PaymentMethodDetailView({ paymentMethod }: PaymentMethodDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(paymentMethod.created_at)
  const updatedAt = formatDateTime(paymentMethod.updated_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={paymentMethod.name} icon={<CreditCard />} />}
        title={paymentMethod.name}
        subtitle={paymentMethod.code}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('paymentMethods.detail.payment_method_code')}>
            {paymentMethod.payment_method_code ? (
              paymentMethod.payment_method_code
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('paymentMethods.detail.description')}>
            {paymentMethod.description ? paymentMethod.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('paymentMethods.detail.payment_instructions')}>
            {paymentMethod.payment_instructions ? (
              paymentMethod.payment_instructions
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('paymentMethods.detail.payment_days')}>
            {paymentMethod.payment_days !== null ? paymentMethod.payment_days : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('paymentMethods.detail.sort_order')}>
            {paymentMethod.sort_order}
          </DetailField>
          <DetailField label={t('paymentMethods.detail.is_active')}>
            {paymentMethod.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {paymentMethod.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="payment-methods" id={paymentMethod.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('paymentMethods.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
      {updatedAt ? (
        <DetailMeta label={t('paymentMethods.detail.updated_at')}>{updatedAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
