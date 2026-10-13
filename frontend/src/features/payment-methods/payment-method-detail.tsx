import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { CreditCard } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { PaymentMethodInstallmentRows } from '@/features/payment-methods/payment-method-installment-rows'
import { PaymentMethodRows } from '@/features/payment-methods/payment-method-rows'
import {
  usePaymentMethodForm,
  type PaymentMethodFormValues,
} from '@/features/payment-methods/use-payment-method-form'
import { formatDateTime } from '@/features/table/cell-renderers'
import type {
  PaymentMethodDetailWithPermissions,
  PaymentMethodFormMode,
} from '@/features/payment-methods/types'

interface PaymentMethodDetailViewProps {
  paymentMethod: PaymentMethodDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single payment method on the record kit: identity band (`code`
 * as the subtitle), the "Details" and "Installments" rows, the activity
 * card, a metadata footer. There is no edit page (wave 3 of the "no edit
 * pages" plan): every row edits IN PLACE, one at a time, each save a PATCH
 * of that row's field(s) alone. The server-managed order stays read-only.
 */
export function PaymentMethodDetailView(props: PaymentMethodDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.paymentMethod.permissions}>
      <PaymentMethodDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function PaymentMethodDetailContent({ paymentMethod, onChanged }: PaymentMethodDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<PaymentMethodFormMode>(() => ({ type: 'edit', paymentMethod }), [paymentMethod])
  const { form, onSubmit, serverError, clearServerError } = usePaymentMethodForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<PaymentMethodFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('paymentMethods.form.genericError'),
  })
  const canEdit = paymentMethod.permissions.resource.update
  const createdAt = formatDateTime(paymentMethod.created_at)
  const updatedAt = formatDateTime(paymentMethod.updated_at)
  const canViewActivity = paymentMethod.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={canViewActivity ? [activityLogTab('payment-methods', paymentMethod.id, t('activityLog.title'))] : []}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={paymentMethod.name} icon={<CreditCard />} />}
            title={paymentMethod.name}
            legacyId={paymentMethod.old_id}
            subtitle={paymentMethod.code}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('paymentMethods.form.sections.identity.title')} full>
              <RecordFieldList>
                <PaymentMethodRows inline={inline} canEdit={canEdit} />
                <RecordField label={t('paymentMethods.detail.sort_order')}>{paymentMethod.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('paymentMethods.form.sections.installments.title')} full>
              <RecordFieldList>
                <PaymentMethodInstallmentRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
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
