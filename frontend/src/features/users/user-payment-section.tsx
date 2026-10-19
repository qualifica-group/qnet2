import { useTranslation } from 'react-i18next'
import { Landmark } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { formatEuro } from '@/features/invoices/invoice-format'
import { formatIban } from '@/features/users/iban'
import { UserPaymentTextField } from '@/features/users/user-employment-fields'
import type { UserRecordSectionProps } from '@/features/users/user-record'

/**
 * "Pagamento" (spec 0272 D-6): the account the person is paid on, IBAN shown in groups of four, and the
 * hourly cost the salary in force today amounts to (spec 0277 D-5), read-only.
 */
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
        <RecordField label={t('users.detail.employment.hourlyCost')}>
          {values.hourly_cost !== null ? `${formatEuro(values.hourly_cost)}${t('users.detail.employment.hourlyCostPerHour')}` : <DetailEmpty />}
          <span className="block text-xs text-muted-foreground">{t('users.detail.employment.hourlyCostHint')}</span>
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}
