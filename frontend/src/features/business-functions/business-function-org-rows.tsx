import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import type { BusinessFunctionRecordValues } from '@/features/business-functions/business-function-record'
import type { BusinessFunctionFormValues } from '@/features/business-functions/use-business-function-form'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'

interface OrgRowProps {
  inline: InlineEdit
  canEdit: boolean
  values: BusinessFunctionRecordValues
}

/** The companies, synced as a whole set: linked badges when closed, the multi picker when open. */
export function BusinessFunctionCompaniesRow({ inline, canEdit, values }: OrgRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<BusinessFunctionFormValues>()

  return (
    <RecordInlineField
      field="company_ids"
      label={t('businessFunctions.form.companies')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationMultiSelectField
          control={control}
          name="company_ids"
          metaKey="company_ids"
          label={t('businessFunctions.form.companies')}
          resource={COMPANIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('businessFunctions.form.companiesSearch')}
          selected={values.companies.map((company) => ({ id: company.id, name: company.label }))}
          placeholder={t('businessFunctions.form.companiesPlaceholder')}
          emptyLabel={t('businessFunctions.form.companiesEmpty')}
          errorLabel={t('businessFunctions.form.companiesError')}
          removeLabel={t('businessFunctions.form.companiesRemove')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {values.companies.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {values.companies.map((company) => (
            <Badge key={company.id} variant="outline" className="max-w-full">
              <RecordLink domain="companies" id={company.id}>
                {company.label}
              </RecordLink>
            </Badge>
          ))}
        </div>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}
