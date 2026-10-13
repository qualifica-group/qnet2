import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Building2 } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { CompanyRows } from '@/features/companies/company-rows'
import { useCompanyForm } from '@/features/companies/use-company-form'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { useGeoNames } from '@/features/geo/use-geo-names'
import type { CompanyDetail } from '@/features/companies/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const COMPANY_FORM_ID = 'company-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface CompanyFormBodyProps {
  onSuccess: (company: CompanyDetail) => void
  onCancel: () => void
}

/**
 * The company create form as a replica of the detail (wave 3 of the "no edit
 * pages" plan): the same rows, closed until clicked (Fatto/Ripristina, nothing
 * saved per row); Salva validates and creates everything at once. The
 * identity band follows the denomination being typed.
 */
export function CompanyFormBody({ onSuccess, onCancel }: CompanyFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useCompanyForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const [denomination, countryId, stateId, provinceId, cityId, customFields] = useWatch({
    control: form.control,
    name: [
      'denomination',
      'address.country_id',
      'address.state_id',
      'address.province_id',
      'address.city_id',
      'custom_fields',
    ],
  })
  const geo = useGeoNames({
    country_id: countryId ?? null,
    state_id: stateId ?? null,
    province_id: provinceId ?? null,
    city_id: cityId ?? null,
  })
  const place = [geo.city, geo.province, geo.state, geo.country].flatMap((level) => (level ? [level.name] : []))
  const heading = denomination.trim() || t('companies.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={COMPANY_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('companies.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Building2 />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('companies.form.save'),
          saving: t('companies.form.saving'),
          cancel: t('companies.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <CompanyRows form={form} inline={draft} place={place} />
          <CustomFieldRecordSections
            resource="companies"
            values={(customFields ?? {}) as Record<string, CustomFieldValue>}
            inline={draft}
            control={form.control}
          />
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
