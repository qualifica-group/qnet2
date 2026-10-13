import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { CreditCard } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { PaymentMethodInstallmentRows } from '@/features/payment-methods/payment-method-installment-rows'
import { PaymentMethodRows } from '@/features/payment-methods/payment-method-rows'
import { usePaymentMethodForm } from '@/features/payment-methods/use-payment-method-form'
import type { PaymentMethodDetail } from '@/features/payment-methods/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const PAYMENT_METHOD_FORM_ID = 'payment-method-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface PaymentMethodFormBodyProps {
  onSuccess: (paymentMethod: PaymentMethodDetail) => void
  onCancel: () => void
}

/**
 * The payment method create form as a replica of the detail (wave 3 of the
 * "no edit pages" plan): the same rows, closed until clicked (Fatto/
 * Ripristina, nothing saved per row), plus the create-only `code`; Salva
 * validates and creates everything at once.
 */
export function PaymentMethodFormBody({ onSuccess, onCancel }: PaymentMethodFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = usePaymentMethodForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('paymentMethods.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={PAYMENT_METHOD_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('paymentMethods.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<CreditCard />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('paymentMethods.form.save'),
          saving: t('paymentMethods.form.saving'),
          cancel: t('paymentMethods.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('paymentMethods.form.sections.identity.title')} full>
            <RecordFieldList>
              <PaymentMethodRows inline={draft} canEdit withCode />
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('paymentMethods.form.sections.installments.title')} full>
            <RecordFieldList>
              <PaymentMethodInstallmentRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
