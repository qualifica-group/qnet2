import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { MapPin } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { useGeoNames } from '@/features/geo/use-geo-names'
import { OperationalSiteRows } from '@/features/operational-sites/operational-site-rows'
import { useOperationalSiteForm } from '@/features/operational-sites/use-operational-site-form'
import type { OperationalSiteDetail } from '@/features/operational-sites/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const OPERATIONAL_SITE_FORM_ID = 'operational-site-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface OperationalSiteFormBodyProps {
  onSuccess: (operationalSite: OperationalSiteDetail) => void
  onCancel: () => void
}

/**
 * The operational-site create form as a replica of the detail (wave 3 of the
 * "no edit pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once. A
 * site has no name of its own, so the identity band follows the alias, or the
 * street while there is none.
 */
export function OperationalSiteFormBody({ onSuccess, onCancel }: OperationalSiteFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useOperationalSiteForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const [alias, line1, countryId, stateId, provinceId, cityId, customFields] = useWatch({
    control: form.control,
    name: ['alias', 'line1', 'country_id', 'state_id', 'province_id', 'city_id', 'custom_fields'],
  })
  const geo = useGeoNames({ country_id: countryId, state_id: stateId, province_id: provinceId, city_id: cityId })
  const heading = alias?.trim() || line1.trim() || t('operationalSites.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={OPERATIONAL_SITE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('operationalSites.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<MapPin />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('operationalSites.form.save'),
          saving: t('operationalSites.form.saving'),
          cancel: t('operationalSites.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <OperationalSiteRows form={form} inline={draft} geo={geo} />
          <CustomFieldRecordSections
            resource="operational-sites"
            values={(customFields ?? {}) as Record<string, CustomFieldValue>}
            inline={draft}
            control={form.control}
          />
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
