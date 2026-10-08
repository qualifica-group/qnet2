import { useTranslation } from 'react-i18next'
import { Building2 } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { enumLabelOf } from '@/features/config/enum-label'
import {
  RegistryAgreementNotesField,
  RegistryEmployeeCountField,
  RegistryEnumField,
  RegistrySwitchField,
  RegistryVatGroupField,
} from '@/features/registries/registry-business-fields'
import type { RegistryRecordSectionProps } from '@/features/registries/registry-record'

function TextValue({ value }: { value: string | null }) {
  return value !== null && value.trim() !== '' ? <span className="whitespace-pre-line">{value}</span> : <DetailEmpty />
}

/**
 * "Dati commerciali" of the anagrafica record (spec 0200), every row editing
 * in place. "Fornitore qualificato" only exists while the anagrafica is a
 * supplier, exactly as the form hid it: turning "Fornitore" off clears it in
 * the same PATCH (`buildUpdatePayload`).
 */
export function RegistryBusinessRecordSection({ values, form, inline }: RegistryRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const yesNo = (value: boolean) => (value ? t('common.yes') : t('common.no'))

  return (
    <RecordSection title={t('registries.form.sections.business.title')} icon={<Building2 />}>
      <RecordFieldList>
        <RecordInlineField
          field="vat_group"
          label={t('registries.form.vatGroup')}
          inline={inline}
          editor={<RegistryVatGroupField control={control} />}
        >
          <TextValue value={values.vat_group} />
        </RecordInlineField>
        <RecordInlineField
          field="is_supplier"
          label={t('registries.form.isSupplier')}
          inline={inline}
          editor={<RegistrySwitchField control={control} name="is_supplier" label={t('registries.form.isSupplier')} />}
        >
          {yesNo(values.is_supplier)}
        </RecordInlineField>
        {values.is_supplier ? (
          <RecordInlineField
            field="is_qualified_supplier"
            label={t('registries.form.isQualifiedSupplier')}
            inline={inline}
            editor={
              <RegistrySwitchField
                control={control}
                name="is_qualified_supplier"
                label={t('registries.form.isQualifiedSupplier')}
              />
            }
          >
            {yesNo(values.is_qualified_supplier)}
          </RecordInlineField>
        ) : null}
        <RecordInlineField
          field="agreement_status"
          label={t('registries.form.agreementStatus')}
          inline={inline}
          editor={
            <RegistryEnumField
              control={control}
              name="agreement_status"
              enumKey="agreement_status"
              label={t('registries.form.agreementStatus')}
              placeholder={t('registries.form.agreementStatusPlaceholder')}
            />
          }
        >
          {values.agreement_status ? enumLabelOf('agreement_status', values.agreement_status) : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="size_class"
          label={t('registries.form.sizeClass')}
          inline={inline}
          editor={
            <RegistryEnumField
              control={control}
              name="size_class"
              enumKey="size_class"
              label={t('registries.form.sizeClass')}
              placeholder={t('registries.form.sizeClassPlaceholder')}
            />
          }
        >
          {values.size_class ? enumLabelOf('size_class', values.size_class) : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="employee_count"
          label={t('registries.form.employeeCount')}
          inline={inline}
          editor={<RegistryEmployeeCountField control={control} />}
        >
          {values.employee_count ?? <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="agreement_notes"
          label={t('registries.form.agreementNotes')}
          inline={inline}
          editor={<RegistryAgreementNotesField control={control} />}
        >
          <TextValue value={values.agreement_notes} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
