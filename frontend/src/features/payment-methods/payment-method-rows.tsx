import { useTranslation } from 'react-i18next'
import { NumberInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { PaymentMethodFormValues } from '@/features/payment-methods/use-payment-method-form'

interface PaymentMethodRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** `code` is create-only (D-3): a row on the draft, the identity band's subtitle on the detail. */
  withCode?: boolean
}

/** The method's "Details" rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function PaymentMethodRows({ inline, canEdit, withCode = false }: PaymentMethodRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<PaymentMethodFormValues> name="name" label={t('paymentMethods.form.name')} inline={inline} canEdit={canEdit} />
      {withCode ? (
        <TextInlineRow<PaymentMethodFormValues> name="code" label={t('paymentMethods.form.code')} inline={inline} canEdit={canEdit} />
      ) : null}
      <TextInlineRow<PaymentMethodFormValues>
        name="payment_method_code"
        label={t('paymentMethods.form.paymentMethodCode')}
        hint={t('paymentMethods.form.hints.paymentMethodCode')}
        inline={inline}
        canEdit={canEdit}
      />
      <TextInlineRow<PaymentMethodFormValues>
        name="description"
        label={t('paymentMethods.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <TextInlineRow<PaymentMethodFormValues>
        name="payment_instructions"
        label={t('paymentMethods.form.paymentInstructions')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <NumberInlineRow<PaymentMethodFormValues>
        name="payment_days"
        label={t('paymentMethods.form.paymentDays')}
        inline={inline}
        canEdit={canEdit}
        min={0}
        max={3650}
        step={1}
      />
      <SwitchInlineRow<PaymentMethodFormValues>
        name="is_active"
        label={t('paymentMethods.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}
