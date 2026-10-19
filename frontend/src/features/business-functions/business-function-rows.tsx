import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch, type UseFormReturn } from 'react-hook-form'
import { Building2, MapPin, UserCog } from 'lucide-react'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import type { BusinessFunctionRecordValues } from '@/features/business-functions/business-function-record'
import { BusinessFunctionCompaniesRow } from '@/features/business-functions/business-function-org-rows'
import {
  BusinessFunctionManagerRow,
  BusinessFunctionParentRow,
  BusinessFunctionSitesRow,
  BusinessFunctionUsersRow,
} from '@/features/business-functions/business-function-relation-rows'
import { BUSINESS_FUNCTION_TYPES, type BusinessFunctionType } from '@/features/business-functions/types'
import type { BusinessFunctionFormValues } from '@/features/business-functions/use-business-function-form'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'

/** Radix `Select` cannot hold an empty-string value: `type=null` ("no type") is represented by this sentinel and translated at the RHF boundary. */
const NONE_TYPE_VALUE = 'none'

/** i18n key per domain type, kept out of the JSX so the option list stays a plain map. */
const TYPE_LABEL_KEYS: Record<BusinessFunctionType, string> = {
  business_unit: 'businessFunctions.form.type.businessUnit',
  business_service: 'businessFunctions.form.type.businessService',
}

interface RowProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The mutually exclusive type (business unit / business service / none). */
function BusinessFunctionTypeRow({ inline, canEdit }: RowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<BusinessFunctionFormValues>()
  const type = useWatch({ control, name: 'type' })

  return (
    <RecordInlineField
      field="type"
      label={t('businessFunctions.form.type.label')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name="type" metaKey="type" label={t('businessFunctions.form.type.label')}>
          {({ field, disabled }) => (
            <Select
              value={field.value ?? NONE_TYPE_VALUE}
              onValueChange={(next) => field.onChange(next === NONE_TYPE_VALUE ? null : (next as BusinessFunctionType))}
              disabled={disabled}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                <SelectItem value={NONE_TYPE_VALUE}>{t('businessFunctions.form.type.none')}</SelectItem>
                {BUSINESS_FUNCTION_TYPES.map((option) => (
                  <SelectItem key={option} value={option}>
                    {t(TYPE_LABEL_KEYS[option])}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      }
    >
      {type ? t(TYPE_LABEL_KEYS[type]) : t('businessFunctions.form.type.none')}
    </RecordInlineField>
  )
}

interface BusinessFunctionSectionsProps extends RowProps {
  form: UseFormReturn<BusinessFunctionFormValues>
  /** The relations and custom fields the closed rows show: the saved record's on the detail, the draft's on a create. */
  values: BusinessFunctionRecordValues
}

/** The business function's sections, shared by the detail (saved one row at a time) and the create draft (kept until Salva). */
export function BusinessFunctionSections({ form, inline, canEdit, values }: BusinessFunctionSectionsProps) {
  const { t } = useTranslation()
  const rowProps = { inline, canEdit, values }

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('businessFunctions.form.sections.identity.title')} full>
        <RecordFieldList>
          <TextInlineRow<BusinessFunctionFormValues>
            name="name"
            label={t('businessFunctions.form.name')}
            inline={inline}
            canEdit={canEdit}
          />
          <BusinessFunctionTypeRow inline={inline} canEdit={canEdit} />
          <BusinessFunctionParentRow {...rowProps} />
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('businessFunctions.form.sections.assignment.title')} icon={<UserCog />}>
        <RecordFieldList>
          <BusinessFunctionManagerRow {...rowProps} />
          <BusinessFunctionUsersRow {...rowProps} />
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('businessFunctions.form.sections.organization.title')} icon={<Building2 />}>
        <RecordFieldList>
          <BusinessFunctionCompaniesRow {...rowProps} />
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('businessFunctions.form.sections.locations.title')} icon={<MapPin />}>
        <RecordFieldList>
          <BusinessFunctionSitesRow {...rowProps} />
        </RecordFieldList>
      </RecordSection>

      <CustomFieldRecordSections resource="business-functions" values={values.custom_fields} inline={inline} control={form.control} />
    </RecordSectionsGrid>
  )
}
