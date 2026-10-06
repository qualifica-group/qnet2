import { useTranslation } from 'react-i18next'
import { Building2, CreditCard } from 'lucide-react'
import type { UseFormReturn } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { QuoteLayoutField, QuotePaymentMethodField } from '@/features/quotes/quote-identity-fields'
import {
  QuoteCompanyField,
  QuoteCompanySiteField,
  QuoteOperationalSiteField,
} from '@/features/quotes/quote-relation-fields'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'

interface LinkedValueProps {
  domain: string
  record: RelationFieldRef | null
}

/** A linked record as the row's value, or the kit's empty placeholder. */
function LinkedValue({ domain, record }: LinkedValueProps) {
  return record ? (
    <RecordLink domain={domain} id={record.id}>
      {record.name}
    </RecordLink>
  ) : (
    <DetailEmpty />
  )
}

interface QuoteCompanySectionProps {
  company: RelationFieldRef | null
  companySite: RelationFieldRef | null
  /** The site IS its primary address: its `label` travels as `name`. */
  operationalSite: RelationFieldRef | null
  form: UseFormReturn<QuoteFormValues>
  inline: InlineEdit
}

/**
 * "Societa' e sedi" (user directive 2026-07-30), every row in place (spec
 * 0197). Changing the Societa' clears the Societa' sede in the same save
 * (`QuoteCompanyField`), and the sede picker is scoped to the Societa' held.
 */
export function QuoteCompanySection({ company, companySite, operationalSite, form, inline }: QuoteCompanySectionProps) {
  const { t } = useTranslation()
  const { control, setValue } = form

  return (
    <RecordSection title={t('quotes.form.sections.sites.title')} icon={<Building2 />}>
      <RecordFieldList>
        <RecordInlineField
          field="company_id"
          label={t('quotes.detail.company')}
          inline={inline}
          editor={<QuoteCompanyField control={control} selected={company} setValue={setValue} />}
        >
          <LinkedValue domain="companies" record={company} />
        </RecordInlineField>
        <RecordInlineField
          field="company_site_id"
          label={t('quotes.detail.companySite')}
          inline={inline}
          editor={<QuoteCompanySiteField control={control} selected={companySite} />}
        >
          <LinkedValue domain="company-sites" record={companySite} />
        </RecordInlineField>
        <RecordInlineField
          field="operational_site_id"
          label={t('quotes.detail.operationalSite')}
          inline={inline}
          editor={<QuoteOperationalSiteField control={control} selected={operationalSite} />}
        >
          <LinkedValue domain="operational-sites" record={operationalSite} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface QuoteDocumentSectionProps {
  layout: RelationFieldRef | null
  paymentMethod: RelationFieldRef | null
  form: UseFormReturn<QuoteFormValues>
  inline: InlineEdit
}

/** "Documento e pagamento": the generation layout (spec 0070) and the agreed payment method, in place. */
export function QuoteDocumentSection({ layout, paymentMethod, form, inline }: QuoteDocumentSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('quotes.detail.sections.document')} icon={<CreditCard />}>
      <RecordFieldList>
        <RecordInlineField
          field="layout_id"
          label={t('quotes.detail.layout')}
          inline={inline}
          editor={<QuoteLayoutField control={control} selected={layout} />}
        >
          {layout ? layout.name : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="payment_method_id"
          label={t('quotes.detail.paymentMethod')}
          inline={inline}
          editor={<QuotePaymentMethodField control={control} selected={paymentMethod} />}
        >
          {paymentMethod ? paymentMethod.name : <DetailEmpty />}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
