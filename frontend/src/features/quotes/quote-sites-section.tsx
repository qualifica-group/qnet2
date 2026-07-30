import { useTranslation } from 'react-i18next'
import { useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { Building2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteDetail } from '@/features/quotes/types'

interface QuoteSitesSectionProps {
  control: Control<QuoteFormValues>
  setValue: UseFormSetValue<QuoteFormValues>
  /** The loaded quote in edit mode, for the pickers' hydrated labels. */
  original: QuoteDetail | null
  /** The sede operativa inherited from the picked Opportunity, when it wins over `original` (create flow). */
  inheritedOperationalSite: RelationFieldRef | null
  labels: {
    placeholder: string
    emptyLabel: string
    errorLabel: string
    clearLabel: string
    retryLabel: string
  }
}

/**
 * Societa' / Societa' Sede / Sede operativa (user directive 2026-07-30).
 *
 * Extracted from `QuoteFormBody` (already at the 300-line soft limit,
 * engineering.md §6) rather than inlined. The Societa' Sede picker is SCOPED
 * to the chosen Societa' (`company_id` dependency param, the same mechanism
 * the project form uses for business function -> product category) and is
 * cleared whenever the Societa' changes, so the pair can never drift — the
 * server rejects a mismatched pair anyway (ValidatesQuoteCompanySite), this
 * only keeps the user from ever building one.
 */
export function QuoteSitesSection({
  control,
  setValue,
  original,
  inheritedOperationalSite,
  labels,
}: QuoteSitesSectionProps) {
  const { t } = useTranslation()
  const companyId = useWatch({ control, name: 'company_id' })

  const operationalSiteRef: RelationFieldRef | null =
    inheritedOperationalSite ??
    (original?.operational_site
      ? { id: original.operational_site.id, name: original.operational_site.label }
      : null)

  return (
    <FormSection
      icon={Building2}
      title={t('quotes.form.sections.sites.title')}
      description={t('quotes.form.sections.sites.description')}
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <RelationSelectField
          control={control}
          name="company_id"
          metaKey="company_id"
          label={t('quotes.form.company')}
          resource={COMPANIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('quotes.form.companySearch')}
          selected={original?.company ?? null}
          onValueChange={(next) => {
            if (next !== companyId) {
              setValue('company_site_id', null, { shouldDirty: true })
            }
          }}
          {...labels}
        />

        <RelationSelectField
          control={control}
          name="company_site_id"
          metaKey="company_site_id"
          label={t('quotes.form.companySite')}
          hint={t('quotes.form.hints.companySite')}
          resource={COMPANY_SITES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('quotes.form.companySiteSearch')}
          selected={original?.company_site ?? null}
          forceDisabled={companyId === null}
          params={companyId !== null ? { company_id: companyId } : undefined}
          {...labels}
        />

        <RelationSelectField
          control={control}
          name="operational_site_id"
          metaKey="operational_site_id"
          label={t('quotes.form.operationalSite')}
          hint={t('quotes.form.hints.operationalSite')}
          resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('quotes.form.operationalSiteSearch')}
          selected={operationalSiteRef}
          {...labels}
        />
      </div>
    </FormSection>
  )
}
