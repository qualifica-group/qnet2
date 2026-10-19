import { useTranslation } from 'react-i18next'
import { Landmark } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { formatIban } from '@/features/users/iban'
import { UserPaymentTextField } from '@/features/users/user-employment-fields'
import type { UserRecordSectionProps } from '@/features/users/user-record'

/** "Pagamento" (spec 0272 D-6): the account the person is paid on, IBAN shown in groups of four. */
export function UserPaymentRecordSection({ values, form, inline }: UserRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const ibanLabel = t('users.detail.employment.iban')
  const holderLabel = t('users.detail.employment.ibanHolder')

  return (
    <RecordSection title={t('users.form.sections.payment.title')} icon={<Landmark />}>
      <RecordFieldList>
        <RecordInlineField
          field="employment.iban"
          label={ibanLabel}
          inline={inline}
          editor={<UserPaymentTextField control={control} name="employment.iban" label={ibanLabel} />}
        >
          {values.iban ? <span className="font-mono">{formatIban(values.iban)}</span> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="employment.iban_holder"
          label={holderLabel}
          inline={inline}
          editor={<UserPaymentTextField control={control} name="employment.iban_holder" label={holderLabel} />}
        >
          {values.iban_holder || <DetailEmpty />}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
